<?php

use App\Enums\TenantRole;
use App\Jobs\ClearProductImageUpload;
use App\Jobs\DeleteProductImage;
use App\Jobs\ProcessProductImage;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['filesystems.disks.r2.key' => 'test-key', 'filesystems.disks.r2.secret' => 'test-secret', 'filesystems.disks.r2.bucket' => 'test-bucket', 'filesystems.disks.r2.endpoint' => 'https://r2.example.test']);
});

test('image uploads get a tenant scoped signed R2 destination and delayed cleanup', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    $product = Product::factory()->for($user->currentTenant)->create();
    $disk = Storage::fake('r2');
    $disk->buildTemporaryUploadUrlsUsing(fn ($path) => ['url' => 'https://r2.example.test/'.$path, 'headers' => ['Content-Type' => 'image/png']]);
    Queue::fake([ClearProductImageUpload::class]);

    $response = $this->actingAs($user)->postJson(route('products.images.store', [$user->currentTenant, $product]), [
        'name' => '../../produto.png', 'mime_type' => 'image/png', 'size' => 500,
    ]);

    $image = ProductImage::query()->sole();
    $response->assertCreated()->assertJsonPath('id', $image->id)->assertJsonPath('headers.Content-Type', 'image/png');
    expect($image->path)->toStartWith('tenants/'.$user->current_tenant_id.'/products/'.$product->id.'/')->not->toContain('..');
    expect($image->upload_path)->toStartWith('uploads/tenants/');
    $disk->assertMissing($image->path);
    Queue::assertPushed(ClearProductImageUpload::class, fn ($job) => $job->imageId === $image->id && $job->uploadPath === $image->upload_path && $job->delay->equalTo(now()->addMinutes(10)));
});

test('uploads fail with an actionable error until R2 is configured', function () {
    config(['filesystems.disks.r2.key' => null]);
    $user = User::factory()->create();
    $product = Product::factory()->for($user->currentTenant)->create();

    $this->actingAs($user)->postJson(route('products.images.store', [$user->currentTenant, $product]), [
        'name' => 'produto.png', 'mime_type' => 'image/png', 'size' => 500,
    ])->assertUnprocessable()->assertJsonValidationErrors(['image' => 'Configure o armazenamento R2 antes de enviar imagens.']);

    $this->assertDatabaseCount('product_images', 0);
});

test('unsupported or oversized image uploads are rejected', function (string $field, mixed $value, string $message) {
    $user = User::factory()->create();
    $product = Product::factory()->for($user->currentTenant)->create();

    $this->actingAs($user)->postJson(route('products.images.store', [$user->currentTenant, $product]), [
        'name' => 'produto.png', 'mime_type' => 'image/png', 'size' => 500, $field => $value,
    ])->assertUnprocessable()->assertJsonValidationErrors([$field => $message]);

    $this->assertDatabaseCount('product_images', 0);
})->with([
    'active SVG' => ['mime_type', 'image/svg+xml', 'Envie uma imagem JPEG, PNG ou WebP.'],
    'oversized file' => ['size', 5242881, 'A imagem deve ter no máximo 5 MB.'],
]);

test('a product cannot reserve more than ten images', function () {
    $user = User::factory()->create();
    $product = Product::factory()->for($user->currentTenant)->create();
    ProductImage::factory()->count(10)->for($product)->create();

    $this->actingAs($user)->postJson(route('products.images.store', [$user->currentTenant, $product]), [
        'name' => 'produto.png', 'mime_type' => 'image/png', 'size' => 500,
    ])->assertUnprocessable()->assertJsonValidationErrors('image');

    $this->assertDatabaseCount('product_images', 10);
});

test('completing an upload queues processing only once', function () {
    $user = User::factory()->create();
    $product = Product::factory()->for($user->currentTenant)->create();
    $image = ProductImage::factory()->for($product)->create();
    Queue::fake([ProcessProductImage::class]);
    $url = route('products.images.complete', [$user->currentTenant, $product, $image]);

    $this->actingAs($user)->post($url)->assertRedirect();
    $this->post($url)->assertRedirect();

    $this->assertDatabaseHas('product_images', ['id' => $image->id, 'status' => 'processing']);
    Queue::assertPushed(ProcessProductImage::class, 1);
});

test('processing verifies the bytes and creates a separate immutable image', function () {
    $disk = Storage::fake('r2');
    $contents = UploadedFile::fake()->image('produto.png', 120, 80)->getContent();
    $image = ProductImage::factory()->processing()->create(['size' => strlen($contents)]);
    $disk->put($image->upload_path, $contents);

    (new ProcessProductImage($image->id))->handle();
    (new ProcessProductImage($image->id))->handle();

    $this->assertDatabaseHas('product_images', ['id' => $image->id, 'status' => 'ready', 'width' => 120, 'height' => 80]);
    $disk->assertExists($image->path);
    $disk->assertMissing($image->upload_path);
    expect($disk->get($image->path))->toBe($contents);
});

test('spoofed image content is rejected and never published', function () {
    $disk = Storage::fake('r2');
    $contents = '<script>alert(1)</script>';
    $image = ProductImage::factory()->processing()->create(['size' => strlen($contents)]);
    $disk->put($image->upload_path, $contents);

    (new ProcessProductImage($image->id))->handle();

    $this->assertDatabaseHas('product_images', ['id' => $image->id, 'status' => 'failed']);
    $disk->assertMissing([$image->path, $image->upload_path]);
});

test('a file larger than its declared size is rejected before publishing', function () {
    $disk = Storage::fake('r2');
    $image = ProductImage::factory()->processing()->create(['size' => 1]);
    $disk->put($image->upload_path, 'oversized');

    (new ProcessProductImage($image->id))->handle();

    $this->assertDatabaseHas('product_images', ['id' => $image->id, 'status' => 'failed']);
    $disk->assertMissing([$image->path, $image->upload_path]);
});

test('files with a different actual image type are rejected', function () {
    $disk = Storage::fake('r2');
    $contents = UploadedFile::fake()->image('produto.jpg')->getContent();
    $image = ProductImage::factory()->processing()->create(['size' => strlen($contents), 'mime_type' => 'image/png']);
    $disk->put($image->upload_path, $contents);

    (new ProcessProductImage($image->id))->handle();

    $this->assertDatabaseHas('product_images', ['id' => $image->id, 'status' => 'failed']);
    $disk->assertMissing($image->path);
});

test('ready images use temporary private URLs on the edit page', function () {
    $user = User::factory()->create();
    $product = Product::factory()->for($user->currentTenant)->create();
    ProductImage::factory()->for($product)->ready()->create();
    $disk = Storage::fake('r2');
    $disk->buildTemporaryUrlsUsing(fn ($path) => 'https://r2.example.test/private/'.$path);

    $this->actingAs($user)->get(route('products.edit', [$user->currentTenant, $product]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('images.0.status', 'ready')->where('images.0.url', fn ($url) => str_starts_with($url, 'https://r2.example.test/private/')));
});

test('image deletion is queued and preserves its record until remote cleanup succeeds', function () {
    $user = User::factory()->create();
    $product = Product::factory()->for($user->currentTenant)->create();
    $image = ProductImage::factory()->for($product)->ready()->create();
    $disk = Storage::fake('r2');
    $disk->put($image->path, 'image');
    $disk->put($image->upload_path, 'staged');
    Queue::fake([DeleteProductImage::class]);

    $this->actingAs($user)->delete(route('products.images.destroy', [$user->currentTenant, $product, $image]))->assertRedirect();

    $this->assertSoftDeleted($image);
    $disk->assertExists($image->path);
    Queue::assertPushed(DeleteProductImage::class, fn ($job) => $job->imageId === $image->id);

    (new DeleteProductImage($image->id))->handle();
    (new DeleteProductImage($image->id))->handle();

    $this->assertModelMissing($image);
    $disk->assertMissing([$image->path, $image->upload_path]);
});

test('cleanup removes a staged file recreated after image deletion', function () {
    $disk = Storage::fake('r2');
    $image = ProductImage::factory()->create();
    $cleanup = new ClearProductImageUpload($image->id, $image->upload_path);
    $image->forceDelete();
    $disk->put($image->upload_path, 'replayed upload');

    $cleanup->handle();

    $disk->assertMissing($image->upload_path);
});

test('delayed cleanup preserves an upload that is still being processed', function () {
    $disk = Storage::fake('r2');
    $image = ProductImage::factory()->processing()->create();
    $disk->put($image->upload_path, 'waiting for retry');

    (new ClearProductImageUpload($image->id, $image->upload_path))->handle();

    $disk->assertExists($image->upload_path);
});

test('another products image cannot be completed or deleted', function (string $method, string $routeName) {
    $user = User::factory()->create();
    $product = Product::factory()->for($user->currentTenant)->create();
    $image = ProductImage::factory()->create();
    Queue::fake();

    $this->actingAs($user)->{$method}(route($routeName, [$user->currentTenant, $product, $image]))->assertNotFound();

    $this->assertModelExists($image);
    Queue::assertNothingPushed();
})->with(['complete' => ['post', 'products.images.complete'], 'delete' => ['delete', 'products.images.destroy']]);

test('members cannot request image upload URLs', function () {
    $user = User::factory()->create();
    $tenant = $user->currentTenant;
    $tenant->members()->updateExistingPivot($user->id, ['role' => TenantRole::Member->value]);
    $product = Product::factory()->for($tenant)->create();

    $this->actingAs($user)->postJson(route('products.images.store', [$tenant, $product]), [
        'name' => 'produto.png', 'mime_type' => 'image/png', 'size' => 500,
    ])->assertForbidden();

    $this->assertDatabaseCount('product_images', 0);
});

test('terminal processing failures become visible to the user', function () {
    $image = ProductImage::factory()->processing()->create();

    (new ProcessProductImage($image->id))->failed(new RuntimeException('storage unavailable'));

    $this->assertDatabaseHas('product_images', ['id' => $image->id, 'status' => 'failed', 'error' => 'Não foi possível processar a imagem. Remova-a e tente enviar novamente.']);
});

test('pruning queues abandoned uploads and recovers stalled processing', function () {
    $this->freezeTime();
    $abandoned = ProductImage::factory()->create(['created_at' => now()->subHours(2)]);
    $stalled = ProductImage::factory()->processing()->create(['created_at' => now()->subHours(2)]);
    $fresh = ProductImage::factory()->create();
    Queue::fake([DeleteProductImage::class, ProcessProductImage::class]);

    $this->artisan('products:prune-images')->assertSuccessful();

    $this->assertSoftDeleted($abandoned);
    $this->assertDatabaseHas('product_images', ['id' => $fresh->id, 'deleted_at' => null]);
    Queue::assertPushed(DeleteProductImage::class, fn ($job) => $job->imageId === $abandoned->id);
    Queue::assertPushed(ProcessProductImage::class, fn ($job) => $job->imageId === $stalled->id);
});
