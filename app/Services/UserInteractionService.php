<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserState;
use App\Models\Transaction;
use App\Models\NeuralNetwork;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram as TelegramFacade;

class UserInteractionService
{
    public function showMainMenu(int $chatId, int $telegramUserId): void
    {
        // Сохраняем пользователя
        $user = User::firstOrCreate(['telegram_id' => $telegramUserId]);
        Log::info('Пользователь найден или сохранен', ['user_id' => $user->id, 'telegram_id' => $telegramUserId]);

        // Устанавливаем состояние пользователя в 'start'
        UserState::updateOrCreate(
            ['user_id' => $user->id],
            ['current_state' => 'start'],
        );
        Log::info('Состояние пользователя start', ['telegram_id' => $telegramUserId]);

        $keyboard = [
            ['Выбрать нейросеть 🔍', 'Мой баланс          💰'],
            ['Пополнить баланс 💳', 'История операций 📋'],
        ];

        $replyKeyboardMarkup = json_encode([
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false, // Клавиатура останется открытой после использования
        ]);

        // Отправляем только клавиатуру без дополнительного текстового сообщения
        TelegramFacade::sendMessage([
            'chat_id' => $chatId,
            'text' => "Выбери действие из меню:",
            'reply_markup' => $replyKeyboardMarkup,
        ]);
    }

    // Для вывода баланса юзера
    public function showUserBalance(int $chatId, $telegramUserId): void
    {
        $user = User::where('telegram_id', $telegramUserId)->first();

        if (!$user) {
            // Логирование и отправка сообщения об ошибке, если пользователь не найден
            Log::error('Пользователь не найден', ['telegramUserId' => $telegramUserId]);
            return;
        }

        $balance = $user->balance ?? 0; // Если баланс не установлен, считать его равным нулю

        $message = "Ваш текущий баланс: {$balance} рублей";
        TelegramFacade::sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
        ]);

        Log::info('Баланс пользователя показан', ['telegramUserId' => $telegramUserId, 'balance' => $balance]);
    }

    // Для вывода транзацкий
    public function showUserTransactions(int $chatId, $telegramUserId): void
    {
        $user = User::where('telegram_id', $telegramUserId)->first();

        if (!$user) {
            Log::error('Пользователь не найден', ['telegramUserId' => $telegramUserId]);
            TelegramFacade::sendMessage([
                'chat_id' => $chatId,
                'text' => "Ошибка: пользователь не найден.",
            ]);
            return;
        }

        $transactions = Transaction::where('user_id', $user->id)->get();

        // Проверяем, существуют ли транзакции и не пустой ли массив транзакций
        if ($transactions === null || $transactions->isEmpty()) {
            $message = "У вас еще нет не каких операций.";
        } else {
            $message = "История ваших транзакций:\n";
            foreach ($user->transactions as $transaction) {
                $message .=
                    "Тип: {$transaction->type}, 
                Сумма: {$transaction->amount}, 
                Дата: " . $transaction->created_at->format('Y-m-d H:i:s') . "\n";
            }
        }

        TelegramFacade::sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
        ]);
        Log::info('Транзацкии пользователя показаны', ['telegramUserId' => $telegramUserId, 'balance' => $transactions]);
    }

    // Выводит список нейросетей
    public function chooseNeuralNetwork(int $chatId): void
    {
        $neuralNetworks = $this->getNeuralNetworks();

        // Создаем кнопки для каждой нейросети, используя названия из $neuralNetworks
        $buttons = array_map(function ($network) {
            return ['text' => $network['name']];
        }, $neuralNetworks);

        // Добавляем кнопку "Назад"
        $backButton = ['text' => 'Назад ◀️'];

        $buttons[] = $backButton;

        $keyboard = array_chunk($buttons, 2);

        $replyKeyboardMarkup = json_encode([
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ]);

        TelegramFacade::sendMessage([
            'chat_id' => $chatId,
            'text' => "Выбери нейросеть:",
            'reply_markup' => $replyKeyboardMarkup,
        ]);
        Log::info('Показан списко нейросетей', ['neuralNetworks' => $neuralNetworks,]);

    }

    // Получаем список нейросетей
    protected function getNeuralNetworks(): array
    {
        return NeuralNetwork::all(['name', 'slug', 'description'])->toArray();
    }
}