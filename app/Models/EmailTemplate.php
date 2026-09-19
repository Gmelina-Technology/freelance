<?php

namespace App\Models;

use App\Enums\EmailTemplateType;
use App\Traits\HasAccount;
use Database\Factories\EmailTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasAccount;

    /** @use HasFactory<EmailTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'type',
        'subject',
        'body',
        'metadata',
    ];

    protected $casts = [
        'type' => EmailTemplateType::class,
        'metadata' => 'array',
    ];

    /**
     * The feature flags this template's type supports, keyed by flag name.
     *
     * @return array<string, array{label: string, description?: string}>
     */
    public function availableFeatures(): array
    {
        return config("email-templates.features.{$this->type?->name}", []);
    }

    /**
     * Whether the given feature flag (declared in config/email-templates.php) is switched on for this template.
     */
    public function featureEnabled(string $feature): bool
    {
        return (bool) data_get($this->metadata, "features.{$feature}", false);
    }
}
