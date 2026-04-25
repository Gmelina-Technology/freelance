<?php

namespace App\Filament\App\Clusters\Settings\Resources\EmailTemplates\Pages;

use App\Filament\App\Clusters\Settings\Resources\EmailTemplates\EmailTemplateResource;
use Filament\Resources\Pages\ManageRecords;

class ManageEmailTemplates extends ManageRecords
{
    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
