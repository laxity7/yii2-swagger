<?php
namespace laxity7\swagger;

use yii\base\ArrayableTrait;

/** Array Helper */
class ArrayHelper
{
	public static function entityToArray($object, $skipNull = true): array
	{
		if (is_array($object)) {
			foreach ($object as $key => $value) {
				if (is_array($value)) {
					$object[$key] = static::entityToArray($value, $skipNull);
				} elseif (is_object($value)) {
					if ($value instanceof ArrayableTrait) {
						$object[$key] = $value->toArray($skipNull);
					} else {
						$object[$key] = static::entityToArray($value, $skipNull);
					}
				} elseif (is_null($value) && $skipNull) {
					unset($object[$key]);
				}
			}

			return $object;
		} elseif (is_object($object)) {
			$result = [];
			foreach ($object as $key => $value) {
				if (is_array($value)) {
					$result[$key] = static::entityToArray($value, $skipNull);
				} elseif (is_object($value)) {
					if ($value instanceof ArrayableTrait) {
						$result[$key] = $value->toArray($skipNull);
					} else {
						$result[$key] = static::entityToArray($value, $skipNull);
					}
				} elseif (!is_null($value) || !$skipNull) {
					$result[$key] = $value;
				}
			}

			return $result;
		} else {
			return [$object];
		}
	}
}
