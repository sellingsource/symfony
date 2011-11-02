<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\DataTransformer;

use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * Transforms between a normalized time and a localized time string/array.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 */
class DateTimeToArrayTransformer extends BaseDateTimeTransformer
{
    private $pad;

    private $fields;

    private $allowPartial;

    private $defaults = array(
        'year'   => 1970,
        'month'  => 1,
        'day'    => 1,
        'hour'   => 0,
        'minute' => 0,
        'second' => 0,
    );

    /**
     * Constructor.
     *
     * @param string  $inputTimezone    The input timezone
     * @param string  $outputTimezone   The output timezone
     * @param array   $fields           The date fields
     * @param Boolean $pad              Whether to use padding
     * @param Boolean $allowPartial     Allow partial dates to be entered in reverseTransform
     * @param array   $defaults         The default values to use for each component if one is not provided
     *
     * @throws UnexpectedTypeException if a timezone is not a string
     */
    public function __construct($inputTimezone = null, $outputTimezone = null, array $fields = null, $pad = false, $allowPartial = true, array $defaults = null)
    {
        parent::__construct($inputTimezone, $outputTimezone);

        if (null === $fields) {
            $fields = array('year', 'month', 'day', 'hour', 'minute', 'second');
        }

        if (null !== $defaults) {
            $this->defaults = array_merge($this->defaults, $defaults);
        }

        $this->fields = $fields;
        $this->pad = (Boolean) $pad;
        $this->allowPartial = (Boolean) $allowPartial;
    }

    /**
     * Transforms a normalized date into a localized date.
     *
     * @param  DateTime $dateTime  Normalized date.
     *
     * @return array               Localized date.
     *
     * @throws UnexpectedTypeException if the given value is not an instance of \DateTime
     * @throws TransformationFailedException if the output timezone is not supported
     */
    public function transform($dateTime)
    {
        if (null === $dateTime) {
            return array_intersect_key(array(
                'year'    => '',
                'month'   => '',
                'day'     => '',
                'hour'    => '',
                'minute'  => '',
                'second'  => '',
            ), array_flip($this->fields));
        }

        if (!$dateTime instanceof \DateTime) {
            throw new UnexpectedTypeException($dateTime, '\DateTime');
        }

        $dateTime = clone $dateTime;
        if ($this->inputTimezone !== $this->outputTimezone) {
            try {
                $dateTime->setTimezone(new \DateTimeZone($this->outputTimezone));
            } catch (\Exception $e) {
                throw new TransformationFailedException($e->getMessage(), $e->getCode(), $e);
            }
        }

        $result = array_intersect_key(array(
            'year'    => $dateTime->format('Y'),
            'month'   => $dateTime->format('m'),
            'day'     => $dateTime->format('d'),
            'hour'    => $dateTime->format('H'),
            'minute'  => $dateTime->format('i'),
            'second'  => $dateTime->format('s'),
        ), array_flip($this->fields));

        if (!$this->pad) {
            foreach ($result as &$entry) {
                // remove leading zeros
                $entry = (string) (int) $entry;
            }
        }

        return $result;
    }

    /**
     * Transforms a localized date into a normalized date.
     *
     * @param  array $value  Localized date
     *
     * @return DateTime      Normalized date
     *
     * @throws UnexpectedTypeException if the given value is not an array
     * @throws TransformationFailedException if the value could not bet transformed
     * @throws TransformationFailedException if the input timezone is not supported
     */
    public function reverseTransform($value)
    {
        if (null === $value) {
            return null;
        }

        if (!is_array($value)) {
            throw new UnexpectedTypeException($value, 'array');
        }

        if (implode('', $value) === '') {
            return null;
        }

        $emptyFields = array();

        foreach ($this->fields as $field) {
            if (!isset($value[$field]) || (!$this->allowPartial && empty($value[$field]))) {
                $emptyFields[] = $field;
            }
        }

        if (count($emptyFields) > 0) {
            throw new TransformationFailedException(
                sprintf('The fields "%s" should not be empty', implode('", "', $emptyFields)
            ));
        }

        if (!empty($value['month']) && !empty($value['day']) && !empty($value['year']) && false === checkdate($value['month'], $value['day'], $value['year'])) {
            throw new TransformationFailedException('This is an invalid date');
        }

        try {
            $dateTime = new \DateTime(sprintf(
                '%s-%s-%s %s:%s:%s %s',
                empty($value['year']) ? $this->defaults['year'] : $value['year'],
                empty($value['month']) ? $this->defaults['month'] : $value['month'],
                empty($value['day']) ? $this->defaults['day'] : $value['day'],
                empty($value['hour']) ? $this->defaults['second'] : $value['hour'],
                empty($value['minute']) ? $this->defaults['minute'] : $value['minute'],
                empty($value['second']) ? $this->defaults['second'] : $value['second'],
                $this->outputTimezone
            ));

            if ($this->inputTimezone !== $this->outputTimezone) {
                $dateTime->setTimezone(new \DateTimeZone($this->inputTimezone));
            }
        } catch (\Exception $e) {
            throw new TransformationFailedException($e->getMessage(), $e->getCode(), $e);
        }

        return $dateTime;
    }
}
