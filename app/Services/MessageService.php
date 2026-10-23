<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Connection;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Chat de texto sin presión: no hay "visto", "en línea" ni "escribiendo".
 */
class MessageService
{
    public function send(Connection $connection, User $sender, string $body): Message
    {
        $recipient = $connection->other($sender);
        $body = trim($body);

        if ($recipient->only_verified_messages && ! $sender->isVerified()) {
            throw new DomainRuleException('recipient_requires_verified', $recipient->name.' sólo recibe mensajes de perfiles verificados.', 403);
        }

        $isFirstMessage = ! $connection->messages()->exists();

        if ($isFirstMessage && $recipient->i_write_first && ! $sender->i_write_first) {
            throw new DomainRuleException('recipient_writes_first', $recipient->name.' prefiere escribir primero.', 403);
        }

        return DB::transaction(function () use ($connection, $sender, $body): Message {
            $message = $connection->messages()->create(['sender_id' => $sender->id, 'body' => $body]);
            $connection->forceFill(['last_message_at' => $message->created_at])->save();

            return $message;
        });
    }
}
