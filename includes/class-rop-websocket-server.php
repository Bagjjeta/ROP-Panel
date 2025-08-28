<?php
if (!defined('ABSPATH')) {
    exit;
}

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class ROP_WebSocket_Server implements MessageComponentInterface
{
    protected $clients;
    protected $users;
    protected $conversations;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->users = array();
        $this->conversations = array();
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    // server/server.php (wewnątrz klasy RopPanelChat)

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);

        // Sprawdź, czy połączenie już jest autoryzowane
        if (!isset($this->clients[$from])) {
            // Jeśli nie, to musi być wiadomość autoryzacyjna
            if (isset($data['type']) && $data['type'] === 'auth' && isset($data['nonce']) && isset($data['user_id'])) {

                // 1. Zweryfikuj nonce
                if (wp_verify_nonce($data['nonce'], 'rop_panel_nonce')) {

                    // 2. Ustaw aktualnego użytkownika
                    $user = get_user_by('id', $data['user_id']);
                    if ($user) {
                        wp_set_current_user($data['user_id']);

                        // 3. Zapisz połączenie jako autoryzowane
                        $this->clients[$from] = ['user_id' => $data['user_id'], 'conn' => $from];

                        // 4. Wyślij potwierdzenie autoryzacji
                        $from->send(json_encode(['type' => 'auth_success']));
                        echo "User {$data['user_id']} connected successfully.\n";

                        // Po udanej autoryzacji przerwij dalsze wykonywanie tej funkcji
                        return;
                    }
                }

                // Jeśli nonce lub user_id są nieprawidłowe - wyślij błąd i zamknij połączenie
                $from->send(json_encode(['type' => 'auth_failed']));
                $from->close();
                echo "Authentication failed. Connection closed.\n";
            } else {
                // Jeśli pierwsza wiadomość nie jest autoryzacją - zamknij połączenie
                $from->close();
            }
            return; // Zakończ, jeśli autoryzacja się nie powiodła
        }

        // Jeśli doszło tutaj, to znaczy, że połączenie jest autoryzowane
        // i można przetwarzać inne typy wiadomości (np. 'send_message')

        // ... Twoja reszta logiki dla wysyłania wiadomości ...
        // (Pamiętaj, aby ją dostosować do nowej struktury)
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);

        // Usuń użytkownika z listy online
        foreach ($this->users as $userId => $connection) {
            if ($connection === $conn) {
                unset($this->users[$userId]);
                $this->broadcastUserStatus($userId, 'offline');
                break;
            }
        }

        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    private function handleAuth($from, $data)
    {
        $token = $data['token'] ?? '';
        $userId = $this->validateToken($token);

        if ($userId) {
            $this->users[$userId] = $from;
            $from->userId = $userId;

            $this->sendToUser($from, [
                'type' => 'auth_success',
                'user_id' => $userId
            ]);

            $this->broadcastUserStatus($userId, 'online');
        } else {
            $this->sendToUser($from, [
                'type' => 'auth_failed',
                'message' => 'Invalid token'
            ]);
        }
    }

    private function handleSendMessage($from, $data)
    {
        if (!isset($from->userId)) {
            return;
        }

        $conversationId = $data['conversation_id'] ?? 0;
        $message = sanitize_text_field($data['message'] ?? '');
        $recipientId = $data['recipient_id'] ?? 0;

        if (empty($message)) {
            return;
        }

        // Jeśli nie ma conversation_id, stwórz nową konwersację
        if (!$conversationId && $recipientId) {
            $conversationId = $this->createConversation($from->userId, $recipientId);
        }

        if (!$conversationId) {
            return;
        }

        // Zapisz wiadomość do bazy danych
        $messageId = $this->saveMessage($conversationId, $from->userId, $message);

        if ($messageId) {
            $messageData = [
                'type' => 'new_message',
                'message_id' => $messageId,
                'conversation_id' => $conversationId,
                'sender_id' => $from->userId,
                'message' => $message,
                'timestamp' => current_time('mysql'),
                'sender_name' => $this->getUserName($from->userId),
                'sender_avatar' => $this->getUserAvatar($from->userId)
            ];

            // Wyślij do wszystkich uczestników konwersacji
            $participants = $this->getConversationParticipants($conversationId);
            foreach ($participants as $participantId) {
                if (isset($this->users[$participantId])) {
                    $this->sendToUser($this->users[$participantId], $messageData);
                }
            }
        }
    }

    private function handleGetConversations($from, $data)
    {
        error_log('Getting conversations for user: ' . $from->userId); // Debug

        if (!isset($from->userId)) {
            return;
        }

        $conversations = $this->getUserConversations($from->userId);

        error_log('Found conversations: ' . print_r($conversations, true)); // Debug

        $this->sendToUser($from, [
            'type' => 'conversations_list',
            'conversations' => $conversations
        ]);
    }

    private function handleGetMessages($from, $data)
    {
        if (!isset($from->userId)) {
            return;
        }

        $conversationId = $data['conversation_id'] ?? 0;
        $page = $data['page'] ?? 1;
        $limit = 20;

        if (!$conversationId) {
            return;
        }

        $messages = $this->getConversationMessages($conversationId, $page, $limit);

        $this->sendToUser($from, [
            'type' => 'messages_list',
            'conversation_id' => $conversationId,
            'messages' => $messages,
            'page' => $page
        ]);
    }

    private function handleMarkRead($from, $data)
    {
        if (!isset($from->userId)) {
            return;
        }

        $conversationId = $data['conversation_id'] ?? 0;

        if ($conversationId) {
            $this->markConversationAsRead($conversationId, $from->userId);
        }
    }

    private function handleTypingStart($from, $data)
    {
        if (!isset($from->userId)) {
            return;
        }

        $conversationId = $data['conversation_id'] ?? 0;

        if ($conversationId) {
            $participants = $this->getConversationParticipants($conversationId);
            foreach ($participants as $participantId) {
                if ($participantId != $from->userId && isset($this->users[$participantId])) {
                    $this->sendToUser($this->users[$participantId], [
                        'type' => 'user_typing',
                        'conversation_id' => $conversationId,
                        'user_id' => $from->userId,
                        'user_name' => $this->getUserName($from->userId)
                    ]);
                }
            }
        }
    }

    private function handleTypingStop($from, $data)
    {
        if (!isset($from->userId)) {
            return;
        }

        $conversationId = $data['conversation_id'] ?? 0;

        if ($conversationId) {
            $participants = $this->getConversationParticipants($conversationId);
            foreach ($participants as $participantId) {
                if ($participantId != $from->userId && isset($this->users[$participantId])) {
                    $this->sendToUser($this->users[$participantId], [
                        'type' => 'user_stopped_typing',
                        'conversation_id' => $conversationId,
                        'user_id' => $from->userId
                    ]);
                }
            }
        }
    }

    private function validateToken($token)
    {
        // Zweryfikuj token JWT lub session token
        // Zwróć user ID jeśli token jest prawidłowy

        // Przykład z WordPress session
        global $wpdb;

        $userId = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
             WHERE meta_key = 'rop_websocket_token' 
             AND meta_value = %s 
             AND user_id > 0",
            $token
        ));

        return $userId ? intval($userId) : false;
    }

    private function createConversation($userId1, $userId2)
    {
        global $wpdb;

        // Sprawdź czy konwersacja już istnieje
        $existingConv = $wpdb->get_var($wpdb->prepare(
            "SELECT conversation_id FROM {$wpdb->prefix}rop_conversation_participants 
             WHERE user_id IN (%d, %d) 
             GROUP BY conversation_id 
             HAVING COUNT(DISTINCT user_id) = 2 
             AND COUNT(*) = 2",
            $userId1,
            $userId2
        ));

        if ($existingConv) {
            return $existingConv;
        }

        // Stwórz nową konwersację
        $wpdb->insert(
            $wpdb->prefix . 'rop_conversations',
            [
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ]
        );

        $conversationId = $wpdb->insert_id;

        // Dodaj uczestników
        $wpdb->insert(
            $wpdb->prefix . 'rop_conversation_participants',
            [
                'conversation_id' => $conversationId,
                'user_id' => $userId1,
                'joined_at' => current_time('mysql')
            ]
        );

        $wpdb->insert(
            $wpdb->prefix . 'rop_conversation_participants',
            [
                'conversation_id' => $conversationId,
                'user_id' => $userId2,
                'joined_at' => current_time('mysql')
            ]
        );

        return $conversationId;
    }

    private function saveMessage($conversationId, $senderId, $message)
    {
        global $wpdb;

        $result = $wpdb->insert(
            $wpdb->prefix . 'rop_messages',
            [
                'conversation_id' => $conversationId,
                'sender_id' => $senderId,
                'message' => $message,
                'sent_at' => current_time('mysql')
            ]
        );

        if ($result) {
            // Aktualizuj czas ostatniej aktywności konwersacji
            $wpdb->update(
                $wpdb->prefix . 'rop_conversations',
                ['updated_at' => current_time('mysql')],
                ['id' => $conversationId]
            );

            return $wpdb->insert_id;
        }

        return false;
    }

    private function getUserConversations($userId)
    {
        global $wpdb;

        $conversations = $wpdb->get_results($wpdb->prepare(
            "SELECT c.id, c.updated_at, 
                    GROUP_CONCAT(DISTINCT cp2.user_id) as participant_ids,
                    (SELECT message FROM {$wpdb->prefix}rop_messages 
                     WHERE conversation_id = c.id 
                     ORDER BY sent_at DESC LIMIT 1) as last_message,
                    (SELECT sent_at FROM {$wpdb->prefix}rop_messages 
                     WHERE conversation_id = c.id 
                     ORDER BY sent_at DESC LIMIT 1) as last_message_time
             FROM {$wpdb->prefix}rop_conversations c
             JOIN {$wpdb->prefix}rop_conversation_participants cp ON c.id = cp.conversation_id
             JOIN {$wpdb->prefix}rop_conversation_participants cp2 ON c.id = cp2.conversation_id
             WHERE cp.user_id = %d
             GROUP BY c.id
             ORDER BY c.updated_at DESC",
            $userId
        ));

        foreach ($conversations as &$conv) {
            $participantIds = explode(',', $conv->participant_ids);
            $otherUserId = null;

            foreach ($participantIds as $pid) {
                if ($pid != $userId) {
                    $otherUserId = $pid;
                    break;
                }
            }

            if ($otherUserId) {
                $conv->other_user_name = $this->getUserName($otherUserId);
                $conv->other_user_avatar = $this->getUserAvatar($otherUserId);
                $conv->other_user_id = $otherUserId;
            }
        }

        return $conversations;
    }

    private function getConversationMessages($conversationId, $page = 1, $limit = 20)
    {
        global $wpdb;

        $offset = ($page - 1) * $limit;

        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, u.display_name as sender_name
             FROM {$wpdb->prefix}rop_messages m
             JOIN {$wpdb->users} u ON m.sender_id = u.ID
             WHERE m.conversation_id = %d
             ORDER BY m.sent_at DESC
             LIMIT %d OFFSET %d",
            $conversationId,
            $limit,
            $offset
        ));

        foreach ($messages as &$message) {
            $message->sender_avatar = $this->getUserAvatar($message->sender_id);
        }

        return array_reverse($messages);
    }

    private function getConversationParticipants($conversationId)
    {
        global $wpdb;

        return $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}rop_conversation_participants 
             WHERE conversation_id = %d",
            $conversationId
        ));
    }

    private function markConversationAsRead($conversationId, $userId)
    {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'rop_conversation_participants',
            ['last_read_at' => current_time('mysql')],
            [
                'conversation_id' => $conversationId,
                'user_id' => $userId
            ]
        );
    }

    private function getUserName($userId)
    {
        $user = get_userdata($userId);
        return $user ? $user->display_name : 'Unknown User';
    }

    private function getUserAvatar($userId)
    {
        return get_avatar_url($userId, ['size' => 50]);
    }

    private function broadcastUserStatus($userId, $status)
    {
        $data = [
            'type' => 'user_status',
            'user_id' => $userId,
            'status' => $status
        ];

        foreach ($this->users as $conn) {
            $this->sendToUser($conn, $data);
        }
    }

    private function sendToUser($conn, $data)
    {
        $conn->send(json_encode($data));
    }
}
