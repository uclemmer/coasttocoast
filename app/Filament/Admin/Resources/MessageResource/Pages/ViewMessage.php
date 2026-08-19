<?php

namespace App\Filament\Admin\Resources\MessageResource\Pages;

use App\Filament\Admin\Resources\MessageResource;
use App\Jobs\SendEventBroadcast;
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Notifications\CampaignMessage;
use App\Services\AudienceBuilder;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Notification as Notifier;
use Illuminate\Support\Str;

/**
 * A campaign, before and after it goes out (doc 07 §3).
 *
 * Three actions, in the order a coordinator uses them: see who it would reach,
 * send it to herself first, then send it.
 */
class ViewMessage extends ViewRecord
{
    protected static string $resource = MessageResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            $this->previewAudienceAction(),
            $this->testSendAction(),
            $this->sendAction(),
        ];
    }

    /**
     * The list, not just the number.
     *
     * A count tells a coordinator whether it looks about right; the names tell
     * her whether the audience she picked is the one she meant, which is the
     * question she actually has before mailing a hundred schools.
     */
    protected function previewAudienceAction(): Action
    {
        return Action::make('previewAudience')
            ->label(__('Who will get this?'))
            ->icon('heroicon-o-users')
            ->color('gray')
            ->modalHeading(__('Recipients'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'))
            ->modalContent(function (Message $record): View {
                $recipients = app(AudienceBuilder::class)->resolve(
                    audience: $record->audience,
                    reference: $record->referenceEvent(),
                    filters: $record->audience_filters ?? [],
                );

                return view('filament.admin.audience-preview', ['recipients' => $recipients]);
            });
    }

    /**
     * Send it to yourself first.
     *
     * Required, not optional (doc 07 §3): a coordinator always wants to see
     * the thing before a hundred schools do. Uses a throwaway recipient row so
     * the real delivery table is not polluted by rehearsals.
     */
    protected function testSendAction(): Action
    {
        return Action::make('testSend')
            ->label(__('Send a test to me'))
            ->icon('heroicon-o-beaker')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('Send a test copy?'))
            ->modalDescription(fn (): string => __('It goes to :email and is not recorded against this campaign.', [
                'email' => (string) auth()->user()?->email,
            ]))
            ->action(function (Message $record): void {
                $rehearsal = new MessageRecipient([
                    'message_id' => $record->getKey(),
                    'email' => (string) auth()->user()?->email,
                ]);
                $rehearsal->id = (string) Str::ulid();

                Notifier::route('mail', auth()->user()?->email)
                    ->notify(new CampaignMessage($record, $rehearsal));

                Notification::make()->title(__('Test sent.'))->success()->send();
            });
    }

    protected function sendAction(): Action
    {
        return Action::make('send')
            ->label(__('Send now'))
            ->icon('heroicon-o-paper-airplane')
            ->color('success')
            // Once sent, gone. There is no unsend.
            ->visible(fn (Message $record): bool => ! $record->isSent())
            ->requiresConfirmation()
            ->modalHeading(__('Send this campaign?'))
            ->modalDescription(function (Message $record): string {
                $count = app(AudienceBuilder::class)->count(
                    $record->audience,
                    $record->referenceEvent(),
                    $record->audience_filters ?? [],
                );

                return __('About :count people will receive this. The exact list is worked out again at the moment it sends. There is no way to unsend it.', [
                    'count' => $count,
                ]);
            })
            ->modalSubmitActionLabel(__('Send it'))
            ->action(function (Message $record): void {
                SendEventBroadcast::dispatch($record);

                Notification::make()
                    ->title(__('Sending.'))
                    ->body(__('Delivery statuses appear below as the messages go out.'))
                    ->success()
                    ->send();
            });
    }
}
