<?php

namespace App\Filament\Admin\Resources;

use App\Enums\Audience;
use App\Enums\MessageChannel;
use App\Filament\Admin\Resources\MessageResource\Pages\CreateMessage;
use App\Filament\Admin\Resources\MessageResource\Pages\EditMessage;
use App\Filament\Admin\Resources\MessageResource\Pages\ListMessages;
use App\Filament\Admin\Resources\MessageResource\Pages\ViewMessage;
use App\Filament\Admin\Resources\MessageResource\RelationManagers\RecipientsRelationManager;
use App\Models\Event;
use App\Models\Message;
use App\Services\AudienceBuilder;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The campaign composer (doc 07 §3, R3.6).
 *
 * A sent message is immutable — no edit page reaches it, and it cannot be
 * deleted. It is the record of what a hundred schools were told and when, and
 * the delivery table hanging off it is only meaningful if the message it
 * describes has not changed since.
 *
 * The audience is stored as a rule rather than a list. The recipients are
 * resolved when the send actually fires, so a note scheduled to lapsed schools
 * reaches whoever is lapsed then (doc 07 §2 rule 6).
 */
class MessageResource extends Resource
{
    protected static ?string $model = Message::class;

    protected static ?string $recordTitleAttribute = 'subject';

    protected static ?int $navigationSort = 50;

    public static function getNavigationLabel(): string
    {
        return __('Campaigns');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('College Fair');
    }

    /**
     * @return array<int, class-string>
     */
    public static function getRelations(): array
    {
        return [
            RecipientsRelationManager::class,
        ];
    }

    /**
     * A sent campaign cannot be edited or deleted: it is the record of what
     * was said to whom.
     */
    public static function canEdit(Model $record): bool
    {
        return $record instanceof Message && ! $record->isSent();
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof Message && ! $record->isSent();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Who'))
                ->schema([
                    Select::make('event_id')
                        ->label(__('Reference fair'))
                        ->options(fn (): array => Event::query()->orderByDesc('starts_at')->pluck('name', 'id')->all())
                        ->live()
                        ->helperText(__('The fair the audience is measured against. Leave blank to use the active one.')),

                    Select::make('audience')
                        ->label(__('Audience'))
                        ->required()
                        ->live()
                        ->options(collect(Audience::cases())
                            ->mapWithKeys(fn (Audience $case): array => [$case->value => $case->getLabel()])
                            ->all())
                        // The definitions in the picker itself, because
                        // "lapsed" means nothing to a coordinator until it is
                        // spelled out.
                        ->descriptions(collect(Audience::cases())
                            ->mapWithKeys(fn (Audience $case): array => [$case->value => $case->getDescription()])
                            ->all()),

                    TextInput::make('preview_count')
                        ->label(__('This will reach'))
                        ->disabled()
                        ->dehydrated(false)
                        // Live, so changing the audience updates the number
                        // before anybody commits to it (doc 07 §3).
                        ->formatStateUsing(fn (Get $get): string => static::previewCount($get))
                        ->helperText(__('Recalculated again when the message actually sends, so this is a guide.')),
                ])
                ->columns(3),

            Section::make(__('What'))
                ->schema([
                    TextInput::make('subject')->required()->maxLength(255)->columnSpanFull(),

                    CheckboxList::make('channels')
                        ->label(__('Send by'))
                        ->options(collect(MessageChannel::cases())
                            ->mapWithKeys(fn (MessageChannel $case): array => [$case->value => $case->getLabel()])
                            ->all())
                        ->default([MessageChannel::Email->value])
                        ->required()
                        ->live()
                        ->columns(2),

                    MarkdownEditor::make('email_body')
                        ->label(__('Email'))
                        ->columnSpanFull()
                        ->visible(fn (Get $get): bool => in_array(MessageChannel::Email->value, (array) $get('channels'), true))
                        ->required(fn (Get $get): bool => in_array(MessageChannel::Email->value, (array) $get('channels'), true)),

                    Textarea::make('sms_body')
                        ->label(__('Text message'))
                        ->rows(3)
                        ->maxLength(320)
                        ->columnSpanFull()
                        ->visible(fn (Get $get): bool => in_array(MessageChannel::Sms->value, (array) $get('channels'), true))
                        ->required(fn (Get $get): bool => in_array(MessageChannel::Sms->value, (array) $get('channels'), true))
                        ->helperText(__('Only reaches people who opted in to texts, and only about fair-day logistics.')),
                ]),

            Section::make(__('When'))
                ->schema([
                    DateTimePicker::make('scheduled_for')
                        ->label(__('Send at'))
                        ->seconds(false)
                        ->helperText(__('Leave blank to send with the button on this page instead.')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject')->searchable()->wrap(),
                TextColumn::make('audience')->badge(),
                TextColumn::make('event.name')->label(__('Fair'))->placeholder(__('Active fair'))->toggleable(),
                TextColumn::make('recipients_count')->label(__('Recipients'))->counts('recipients'),
                TextColumn::make('sent_at')
                    ->label(__('Sent'))
                    ->dateTime()
                    ->placeholder(fn (Message $record): string => $record->scheduled_for
                        ? __('Scheduled for :when', ['when' => $record->scheduled_for->format('j M, g:i A')])
                        : __('Draft'))
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('audience')
                    ->options(collect(Audience::cases())
                        ->mapWithKeys(fn (Audience $case): array => [$case->value => $case->getLabel()])
                        ->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('subject'),
            TextEntry::make('audience')->badge(),
            TextEntry::make('event.name')->label(__('Reference fair'))->placeholder(__('Active fair')),
            TextEntry::make('sent_at')->label(__('Sent'))->dateTime()->placeholder(__('Not sent yet')),
            TextEntry::make('scheduled_for')->label(__('Scheduled for'))->dateTime()->placeholder('—'),
            TextEntry::make('author.name')->label(__('Written by')),
            TextEntry::make('email_body')->label(__('Email'))->markdown()->placeholder('—')->columnSpanFull(),
            TextEntry::make('sms_body')->label(__('Text message'))->placeholder('—')->columnSpanFull(),
        ]);
    }

    /**
     * The live preview count for the form.
     *
     * Wrapped because the composer must survive a half-filled form: an
     * audience chosen before a fair, or a fair with no history yet, should
     * read "0" rather than throw and take the page down.
     */
    protected static function previewCount(Get $get): string
    {
        $audience = Audience::tryFrom((string) $get('audience'));

        if (! $audience instanceof Audience) {
            return __('Choose an audience');
        }

        $event = filled($get('event_id')) ? Event::query()->find($get('event_id')) : Event::active();

        return (string) app(AudienceBuilder::class)->count($audience, $event);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListMessages::route('/'),
            'create' => CreateMessage::route('/create'),
            'view' => ViewMessage::route('/{record}'),
            'edit' => EditMessage::route('/{record}/edit'),
        ];
    }
}
