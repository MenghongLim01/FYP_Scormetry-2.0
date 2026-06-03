<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A single flexible in-app (database) notification. Keeps the notification
 * payload uniform so the bell + notifications page can render any event the
 * same way: an icon, a title, a body, an optional deep link, and a category.
 */
class GenericNotification extends Notification
{
    use Queueable;

    /**
     * @param  'paper'|'review'|'result'|'reviewer'|'schedule'|'deadline'|'system'  $category
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $url = null,
        public readonly string $category = 'system',
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'category' => $this->category,
        ];
    }
}
