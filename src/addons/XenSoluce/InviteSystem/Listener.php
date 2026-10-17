<?php

namespace XenSoluce\InviteSystem;

use XF\Container;
use XF\Template\Templater;

class Listener
{
   	public static function criteriaUser($rule, array $data, \XF\Entity\User $user, &$returnValue)
	{
		switch ($rule)
		{
            case 'min_invite':
                if (isset($user->xs_is_invite_count) && $user->xs_is_invite_count >= $data['invite'])
                {
                    $returnValue = true;
                }
                break;
            case 'max_invite':
                if (isset($user->xs_is_invite_count) && $user->xs_is_invite_count >= $data['invite'])
                {
                    $returnValue = true;
                }
                break;
		}
	}
	public static function userSearcherOrders(\XF\Searcher\User $userSearcher, array &$sortOrders)
	{
		$sortOrders['xs_is_invite_count'] = \XF::phrase('xs_is_most_invitations');
	}

    public static function templaterSetup(Container $container, Templater &$templater)
    {
        $templater->addFunction('convert_to_time_units', [__CLASS__, 'templaterFnConvertToTimeUnits']);
    }
//xs_is_convert_week_and_day
    public static function templaterFnConvertToTimeUnits( $templater, &$escape, $numberOfDays)
    {
        $escape = false;

        $units = [
            'year' => ["value" => 365, 'name' => [
                'one' => [
                    'phrase' => 'xs_is_x_year',
                    'key' => 'year'
                ],
                'many' => [
                    'phrase' => 'x_years',
                    'key' => 'years'
                ]
            ]],
            'month' => ["value" => 30, 'name' => [
                'one' => [
                    'phrase' => 'xs_is_x_month',
                    'key' => 'month'
                ],
                'many' => [
                    'phrase' => 'x_months',
                    'key' => 'months'
                ]
            ]],
            'week' => ["value" => 7, 'name' => [
                'one' => [
                    'phrase' => 'xs_is_x_week',
                    'key' => 'week'
                ],
                'many' => [
                    'phrase' => 'x_weeks',
                    'key' => 'weeks'
                ]
            ]],
            'day' => ["value" => 1, 'name' => [
                'one' => [
                    'phrase' => 'xs_is_x_day',
                    'key' => 'day'
                ],
                'many' => [
                    'phrase' => 'x_days',
                    'key' => 'days'
                ]
            ]],
        ];

        $output = [];

        foreach ($units as $key => $unit) {
            $unitValue = $unit['value'];
            $unitCount = floor($numberOfDays / $unitValue);

            if ($unitCount > 0) {

                $unitName = $unitCount > 1 ? $unit['name']['many'] : $unit['name']['one'];
                $output[$key] = \XF::phrase($unitName['phrase'], [$unitName['key'] => $unitCount]);
                $numberOfDays %= $unitValue;
            }
        }
        if(count($output) === 1) {
            return $output[array_key_first($output)];
        }

        $lastUnit = end($output);
        $lastKey = key($output);
        array_pop($output);

        $phrase = 'xs_is_convert_' . implode('_', array_keys($output));
        $phrase .= '_and_' . $lastKey;
        return \XF::phrase($phrase, $output + [$lastKey => $lastUnit]);
    }
}