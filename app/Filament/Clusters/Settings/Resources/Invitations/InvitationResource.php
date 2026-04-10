<?php

namespace App\Filament\Clusters\Settings\Resources\Invitations;

use App\Filament\Clusters\Settings\Resources\Invitations\Pages\ListInvitations;
use App\Filament\Clusters\Settings\Resources\Invitations\Schemas\InvitationForm;
use App\Filament\Clusters\Settings\Resources\Invitations\Tables\InvitationsTable;
use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\Invitation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InvitationResource extends Resource
{
    protected static ?string $model = Invitation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Envelope;

    protected static ?string $cluster = SettingsCluster::class;

    protected static ?string $recordTitleAttribute = 'email';

    public static function form(Schema $schema): Schema
    {
        return InvitationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvitationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvitations::route('/'),
        ];
    }
}
