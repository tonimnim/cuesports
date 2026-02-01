<?php

namespace App\Constants;

class AfricanCountries
{
    /**
     * All 54 African countries with their ISO codes and phone codes.
     * This is used for the Geography Builder to add countries to the platform.
     */
    public const ALL = [
        ['name' => 'Algeria', 'code' => 'DZ', 'phone_code' => '+213'],
        ['name' => 'Angola', 'code' => 'AO', 'phone_code' => '+244'],
        ['name' => 'Benin', 'code' => 'BJ', 'phone_code' => '+229'],
        ['name' => 'Botswana', 'code' => 'BW', 'phone_code' => '+267'],
        ['name' => 'Burkina Faso', 'code' => 'BF', 'phone_code' => '+226'],
        ['name' => 'Burundi', 'code' => 'BI', 'phone_code' => '+257'],
        ['name' => 'Cameroon', 'code' => 'CM', 'phone_code' => '+237'],
        ['name' => 'Cape Verde', 'code' => 'CV', 'phone_code' => '+238'],
        ['name' => 'Central African Republic', 'code' => 'CF', 'phone_code' => '+236'],
        ['name' => 'Chad', 'code' => 'TD', 'phone_code' => '+235'],
        ['name' => 'Comoros', 'code' => 'KM', 'phone_code' => '+269'],
        ['name' => 'Democratic Republic of the Congo', 'code' => 'CD', 'phone_code' => '+243'],
        ['name' => 'Republic of the Congo', 'code' => 'CG', 'phone_code' => '+242'],
        ['name' => 'Côte d\'Ivoire', 'code' => 'CI', 'phone_code' => '+225'],
        ['name' => 'Djibouti', 'code' => 'DJ', 'phone_code' => '+253'],
        ['name' => 'Egypt', 'code' => 'EG', 'phone_code' => '+20'],
        ['name' => 'Equatorial Guinea', 'code' => 'GQ', 'phone_code' => '+240'],
        ['name' => 'Eritrea', 'code' => 'ER', 'phone_code' => '+291'],
        ['name' => 'Eswatini', 'code' => 'SZ', 'phone_code' => '+268'],
        ['name' => 'Ethiopia', 'code' => 'ET', 'phone_code' => '+251'],
        ['name' => 'Gabon', 'code' => 'GA', 'phone_code' => '+241'],
        ['name' => 'Gambia', 'code' => 'GM', 'phone_code' => '+220'],
        ['name' => 'Ghana', 'code' => 'GH', 'phone_code' => '+233'],
        ['name' => 'Guinea', 'code' => 'GN', 'phone_code' => '+224'],
        ['name' => 'Guinea-Bissau', 'code' => 'GW', 'phone_code' => '+245'],
        ['name' => 'Kenya', 'code' => 'KE', 'phone_code' => '+254'],
        ['name' => 'Lesotho', 'code' => 'LS', 'phone_code' => '+266'],
        ['name' => 'Liberia', 'code' => 'LR', 'phone_code' => '+231'],
        ['name' => 'Libya', 'code' => 'LY', 'phone_code' => '+218'],
        ['name' => 'Madagascar', 'code' => 'MG', 'phone_code' => '+261'],
        ['name' => 'Malawi', 'code' => 'MW', 'phone_code' => '+265'],
        ['name' => 'Mali', 'code' => 'ML', 'phone_code' => '+223'],
        ['name' => 'Mauritania', 'code' => 'MR', 'phone_code' => '+222'],
        ['name' => 'Mauritius', 'code' => 'MU', 'phone_code' => '+230'],
        ['name' => 'Morocco', 'code' => 'MA', 'phone_code' => '+212'],
        ['name' => 'Mozambique', 'code' => 'MZ', 'phone_code' => '+258'],
        ['name' => 'Namibia', 'code' => 'NA', 'phone_code' => '+264'],
        ['name' => 'Niger', 'code' => 'NE', 'phone_code' => '+227'],
        ['name' => 'Nigeria', 'code' => 'NG', 'phone_code' => '+234'],
        ['name' => 'Rwanda', 'code' => 'RW', 'phone_code' => '+250'],
        ['name' => 'São Tomé and Príncipe', 'code' => 'ST', 'phone_code' => '+239'],
        ['name' => 'Senegal', 'code' => 'SN', 'phone_code' => '+221'],
        ['name' => 'Seychelles', 'code' => 'SC', 'phone_code' => '+248'],
        ['name' => 'Sierra Leone', 'code' => 'SL', 'phone_code' => '+232'],
        ['name' => 'Somalia', 'code' => 'SO', 'phone_code' => '+252'],
        ['name' => 'South Africa', 'code' => 'ZA', 'phone_code' => '+27'],
        ['name' => 'South Sudan', 'code' => 'SS', 'phone_code' => '+211'],
        ['name' => 'Sudan', 'code' => 'SD', 'phone_code' => '+249'],
        ['name' => 'Tanzania', 'code' => 'TZ', 'phone_code' => '+255'],
        ['name' => 'Togo', 'code' => 'TG', 'phone_code' => '+228'],
        ['name' => 'Tunisia', 'code' => 'TN', 'phone_code' => '+216'],
        ['name' => 'Uganda', 'code' => 'UG', 'phone_code' => '+256'],
        ['name' => 'Zambia', 'code' => 'ZM', 'phone_code' => '+260'],
        ['name' => 'Zimbabwe', 'code' => 'ZW', 'phone_code' => '+263'],
    ];

    /**
     * Get all countries as array.
     */
    public static function all(): array
    {
        return self::ALL;
    }

    /**
     * Get a country by its code.
     */
    public static function getByCode(string $code): ?array
    {
        foreach (self::ALL as $country) {
            if ($country['code'] === strtoupper($code)) {
                return $country;
            }
        }
        return null;
    }

    /**
     * Get a country by its name.
     */
    public static function getByName(string $name): ?array
    {
        foreach (self::ALL as $country) {
            if (strtolower($country['name']) === strtolower($name)) {
                return $country;
            }
        }
        return null;
    }

    /**
     * Search countries by name (partial match).
     */
    public static function search(string $query): array
    {
        if (empty($query)) {
            return self::ALL;
        }

        $query = strtolower($query);
        return array_values(array_filter(self::ALL, function ($country) use ($query) {
            return str_contains(strtolower($country['name']), $query) ||
                   str_contains(strtolower($country['code']), $query);
        }));
    }

    /**
     * Get all country codes.
     */
    public static function codes(): array
    {
        return array_column(self::ALL, 'code');
    }

    /**
     * Check if a country code exists.
     */
    public static function exists(string $code): bool
    {
        return in_array(strtoupper($code), self::codes());
    }
}
