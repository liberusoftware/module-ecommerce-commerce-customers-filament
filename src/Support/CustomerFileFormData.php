<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CommerceCustomers\Filament\Support;

use Liberu\Ecommerce\CommerceCustomers\Data\PostalAddress;
use Liberu\Ecommerce\CommerceCustomers\Data\ProfileDetails;
use Liberu\Ecommerce\CommerceCustomers\Data\TaxStatus;

/**
 * Form state to the domain's data objects, in one place.
 *
 * A blank text input is an empty string and the domain's DTOs mean null by it:
 * `ProfileDetails` replaces the whole block, so an empty string would store a
 * name that is one space long rather than clearing it, and `TaxStatus` would
 * reject `''` as a malformed registration rather than reading it as none.
 */
final class CustomerFileFormData
{
    /** @param array<string, mixed> $data */
    public static function profile(array $data): ProfileDetails
    {
        return new ProfileDetails(
            givenName: self::text($data, 'given_name'),
            familyName: self::text($data, 'family_name'),
            email: self::text($data, 'email'),
            phone: self::text($data, 'phone'),
            phoneCountry: self::text($data, 'phone_country'),
            locale: self::text($data, 'locale'),
            preferredCurrency: self::text($data, 'preferred_currency'),
            timezone: self::text($data, 'timezone'),
        );
    }

    /** @param array<string, mixed> $data */
    public static function taxStatus(array $data): TaxStatus
    {
        return new TaxStatus(
            exempt: (bool) ($data['tax_exempt'] ?? false),
            registrationId: self::text($data, 'tax_registration_id'),
            registrationCountry: self::text($data, 'tax_registration_country'),
        );
    }

    /** @param array<string, mixed> $data */
    public static function postalAddress(array $data): PostalAddress
    {
        return new PostalAddress(
            lineOne: (string) ($data['line_one'] ?? ''),
            countryCode: strtoupper((string) ($data['country_code'] ?? '')),
            lineTwo: self::text($data, 'line_two'),
            locality: self::text($data, 'locality'),
            administrativeArea: self::text($data, 'administrative_area'),
            postalCode: self::text($data, 'postal_code'),
            recipientName: self::text($data, 'recipient_name'),
            company: self::text($data, 'company'),
        );
    }

    /** @param array<string, mixed> $data */
    public static function text(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
