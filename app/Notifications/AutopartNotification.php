<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

class AutopartNotification extends Notification
{
    use Queueable;

    private $channel;
    private $content;
    private $button;

    /**
     * Create a new notification instance.
     */
    public function __construct($channel, $content, $button = null)
    {
        $this->channel = $channel;
        $this->content = $content;
        $this->button = $button;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ["telegram"];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->line('The introduction to the notification.')
                    ->action('Notification Action', url('/'))
                    ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }

    public function toTelegram($notifiable)
    {
        $message = TelegramMessage::create()
            ->to($this->channel)
            ->content($this->content);

        if ($this->button) {
            $message->button('Ver en AG', 'https://autoglobal.mx/autopart/'.$this->button);
        }

        return $message;
    }
}
