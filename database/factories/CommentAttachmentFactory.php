<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\CommentAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommentAttachment>
 */
class CommentAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'comment_id' => Comment::factory(),
            'disk' => config('comments.attachments.disk'),
            'path' => 'comments/'.$this->faker->uuid.'.txt',
            'original_name' => $this->faker->word.'.txt',
            'mime_type' => 'text/plain',
            'size' => $this->faker->numberBetween(10, 1000),
        ];
    }
}
