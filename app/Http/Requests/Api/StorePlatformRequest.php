<?php

namespace App\Http\Requests\Api;

class StorePlatformRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name'                       => 'required|string|max:255',
            'slug'                       => 'nullable|string|max:100|unique:platforms,slug',
            'logo_url'                   => 'nullable|url',
            'website_url'                => 'nullable|url',
            'category'                   => 'nullable|string|in:marketplace,portal,partner,own_website',
            'export_method'              => 'nullable|string|in:openmarine,xml,api,manual',
            'feed_url'                   => 'nullable|url',
            'api_url'                    => 'nullable|url',
            'credentials'                => 'nullable|array',
            'credentials.api_key'        => 'nullable|string|max:255',
            'credentials.api_secret'     => 'nullable|string|max:255',
            'credentials.webhook_url'    => 'nullable|url',
            'credentials.webhook_secret' => 'nullable|string|max:255',
            'credentials.debug_mode'     => 'nullable|boolean',
            'openmarine_enabled'         => 'boolean',
            'openmarine_dealer_id'       => 'nullable|string|max:100',
            'openmarine_version'         => 'nullable|string|max:10',
            'openmarine_category_map'    => 'nullable|array',
            'supported_countries'        => 'nullable|array',
            'supported_countries.*'      => 'string|max:2',
            'supported_languages'        => 'nullable|array',
            'supported_languages.*'      => 'string|max:2',
            'contact_name'               => 'nullable|string|max:255',
            'contact_email'              => 'nullable|email',
            'notes'                      => 'nullable|string',
            'priority'                   => 'nullable|integer',
            'is_active'                  => 'boolean',
            'feed_source_platform_id'    => 'nullable|integer|exists:platforms,id',
        ];
    }
}
