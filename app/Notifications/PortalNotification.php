<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One notification for every portal event. It is always saved in the
 * bell list, and also emailed when $mail is true.
 */
class PortalNotification extends Notification
{
    /**
     * @param  array<string, int|string>  $params  Route parameters for the page it links to.
     */
    public function __construct(
        public string $title,
        public string $body,
        public string $route,
        public array $params = [],
        public bool $mail = true,
    ) {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->mail ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->body)
            ->action('Open in the portal', route($this->route, $this->params))
            ->salutation(config('portal.name'));
    }

    /**
     * @return array{title: string, body: string, route: string, params: array<string, int|string>}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'route' => $this->route,
            'params' => $this->params,
        ];
    }
}
