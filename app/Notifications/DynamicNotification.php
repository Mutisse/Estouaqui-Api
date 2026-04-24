<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\NotificationTemplate;

class DynamicNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $templateType;
    protected $data;

    public function __construct($templateType, $data = [])
    {
        $this->templateType = $templateType;
        $this->data = $data;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $template = NotificationTemplate::where('type', $this->templateType)
            ->where('active', true)
            ->first();

        if (!$template) {
            return [
                'type' => $this->templateType,
                'titulo' => 'Notificação',
                'mensagem' => 'Nova atualização no sistema',
                'icon' => 'notifications',
                'color' => 'primary',
                'data' => $this->data,
            ];
        }

        // Substituir placeholders no corpo
        $body = $template->body_pt;
        foreach ($this->data as $key => $value) {
            $body = str_replace('{' . $key . '}', (string) $value, $body);
        }

        // Substituir placeholders no título
        $title = $template->title_pt;
        foreach ($this->data as $key => $value) {
            $title = str_replace('{' . $key . '}', (string) $value, $title);
        }

        return [
            'type' => $this->templateType,
            'titulo' => $title,
            'mensagem' => $body,
            'icon' => $template->icon ?? 'notifications',
            'color' => $template->color ?? 'primary',
            'data' => $this->data,
        ];
    }
}
