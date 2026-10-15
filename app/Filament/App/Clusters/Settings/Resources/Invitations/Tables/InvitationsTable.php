<?php

namespace App\Filament\App\Clusters\Settings\Resources\Invitations\Tables;

use App\Enums\AccountCapability;
use App\Models\Invitation;
use App\Services\InvitationService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class InvitationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('role'),
                TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(fn (Invitation $record): string => match (true) {
                        $record->isAccepted() => 'Accepted',
                        $record->isExpired() => 'Expired',
                        default => 'Pending',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Accepted' => 'success',
                        'Expired' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                TextColumn::make('accepted_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('resend')
                    ->label('Resend')
                    ->icon(Heroicon::ArrowPath)
                    ->requiresConfirmation()
                    ->visible(fn (Invitation $record): bool => static::canResend($record))
                    ->authorize(fn (Invitation $record): bool => static::canResend($record))
                    ->action(function (Invitation $record): void {
                        app(InvitationService::class)->resend(Filament::getTenant(), Auth::user(), $record);

                        Notification::make()
                            ->title('Invitation re-sent')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function canResend(Invitation $record): bool
    {
        return $record->isPending()
            && Auth::user()->can(AccountCapability::forRoleChange(null, $record->role), Filament::getTenant());
    }
}
