<?php

namespace App\Filament\Admin\Resources\EventResource\Pages;

use App\Filament\Admin\Resources\EventResource;
use App\Models\Event;
use App\Models\EventInterest;
use App\Notifications\RegistrationOpenAnnouncement;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification as Notifier;

class ViewEvent extends ViewRecord
{
    protected static string $resource = EventResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            $this->announceAction(),
        ];
    }

    /**
     * Tell the notify-me list that registration is open (card 6.5).
     *
     * Sends only to rows with no `notified_at` and stamps each as it goes, so
     * pressing the button twice is harmless. That matters: the realistic
     * mistake is a coordinator who is not sure whether the first press worked,
     * and the answer to that should be "press it again", not a hundred
     * duplicate emails.
     */
    protected function announceAction(): Action
    {
        return Action::make('announceRegistrationOpen')
            ->label(__('Tell the interest list'))
            ->icon('heroicon-o-megaphone')
            ->visible(fn (Event $record): bool => $record->is_published
                && $this->unnotified($record)->exists())
            ->requiresConfirmation()
            ->modalHeading(__('Announce that registration is open?'))
            ->modalDescription(fn (Event $record): string => __(
                ':count people asked to be told about this fair and have not been. Anyone already told '
                .'is skipped, so this is safe to press twice.',
                ['count' => $this->unnotified($record)->count()],
            ))
            ->modalSubmitActionLabel(__('Send it'))
            ->action(function (Event $record): void {
                $waiting = $this->unnotified($record)->get();

                foreach ($waiting as $interest) {
                    Notifier::route('mail', $interest->email)
                        ->notify(new RegistrationOpenAnnouncement($record));

                    // Stamped one at a time rather than in bulk afterwards: if
                    // this dies halfway, the people already mailed are marked
                    // and a re-run picks up where it stopped.
                    $interest->forceFill(['notified_at' => Carbon::now()])->save();
                }

                Notification::make()
                    ->title(trans_choice(
                        '{0}Nobody was waiting.|{1}One person told.|[2,*]:count people told.',
                        $waiting->count(),
                        ['count' => $waiting->count()],
                    ))
                    ->success()
                    ->send();
            });
    }

    /**
     * @return HasMany<EventInterest, Event>
     */
    protected function unnotified(Event $event)
    {
        return $event->interests()->unnotified();
    }
}
