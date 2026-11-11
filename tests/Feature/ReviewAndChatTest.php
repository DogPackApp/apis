<?php

use App\Mail\Chat\ChatNewMessage;
use App\Models\Chat\Chat;
use App\Models\Chat\ChatUser;
use App\Models\Chat\SellerChatReport;
use App\Models\Chat\Thread;
use App\Models\Product\Item;
use App\Models\Review\ItemReview;
use App\Models\Review\ItemReviewReport;
use App\Models\Review\ItemReviewReportReason;
use App\Models\Seller\Seller;
use App\Models\Store\Store;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

test('a seller can list reviews, reply, and report one', function () {
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $item = Item::factory()->create([
        'store_id' => $store->id,
        'status' => Item::STATUS[Item::STATUS_ACTIVE],
    ]);
    $review = ItemReview::query()->create([
        'store_id' => $store->id,
        'item_id' => $item->id,
        'username' => 'Ada',
        'rating' => 5,
        'title' => 'Great toy',
        'comment' => 'The dog loves it',
        'is_visible' => true,
    ]);

    $this->getJson('/api/seller/store/reviews?orderBy=recent&type=all&rating=all&media=all')
        ->assertOk()
        ->assertJsonPath('data.0.username', 'Ada')
        ->assertJsonPath('data.0.rating', 5)
        ->assertJsonPath('totalReviews', 1);

    $this->postJson('/api/seller/reviews/'.$review->uuid.'/reply/', [
        'message' => 'Thank you',
    ])->assertOk();

    expect($review->fresh()->response)->toBe('Thank you');

    $reason = new ItemReviewReportReason;
    $reason->uuid = (string) Str::uuid();
    $reason->title = 'Spam';
    $reason->save();

    $this->getJson('/api/seller/reviews/report/')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Spam');

    $this->postJson('/api/seller/reviews/'.$review->uuid.'/report/', [
        'reason_id' => $reason->uuid,
    ])->assertOk();

    expect(ItemReviewReport::query()->where('item_review_id', $review->id)->exists())->toBeTrue();
});

test('a seller can list a chat, read it, report it, and unblock it', function () {
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $customer = ChatUser::query()->create([
        'first_name' => 'Ada',
        'last_name' => 'Buyer',
        'email' => 'ada@example.com',
    ]);
    $thread = Thread::query()->forceCreate([
        'customer_id' => $customer->uuid,
        'seller_id' => $store->uuid,
        'last_message' => 'Is this in stock?',
        'last_message_at' => now(),
        'is_active' => true,
    ]);
    Chat::query()->forceCreate([
        'thread_uuid' => $thread->uuid,
        'sender_id' => $customer->uuid,
        'message' => 'Is this in stock?',
        'is_read' => 0,
    ]);

    $this->getJson('/api/seller/chat/list')
        ->assertOk()
        ->assertJsonPath('data.0.uuid', (string) $thread->uuid)
        ->assertJsonPath('data.0.unread_count', 1)
        ->assertJsonPath('data.0.customer.first_name', 'Ada');

    $this->getJson('/api/seller/chat/unreadCount')
        ->assertOk()
        ->assertJsonPath('total_unread_count', 1);

    $this->getJson('/api/seller/chat/list/'.$thread->uuid)
        ->assertOk()
        ->assertJsonPath('data.0.message', 'Is this in stock?');

    expect(Chat::query()->first()->is_read)->toBe(1);

    $this->postJson('/api/seller/chat/report/'.$thread->uuid, [
        'reason' => 'Harassment',
    ])->assertCreated();

    expect($thread->fresh()->is_active)->toBeFalsy();
    expect(SellerChatReport::query()->where('thread_uuid', $thread->uuid)->exists())->toBeTrue();

    $this->putJson('/api/seller/chat/unblock/'.$thread->uuid)
        ->assertOk();

    expect($thread->fresh()->is_active)->toBeTruthy();
});

test('a chat email to the seller is sent without a seller token', function () {
    Mail::fake();

    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);

    $this->postJson('/api/seller/seller/chat/email', [
        'receiverType' => 'seller',
        'message' => [
            'sellerUUID' => $store->uuid,
            'name' => 'Ada',
            'message' => 'Hello from the buyer',
        ],
    ])->assertOk();

    Mail::assertQueued(ChatNewMessage::class, function (ChatNewMessage $mail) use ($seller) {
        return $mail->hasTo($seller->email);
    });
});
