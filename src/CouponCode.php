<?php
/**
 * CouponCode
 * Copyright (c) 2014 Atelier Disko. All rights reserved.
 *
 * Modified by Alex Rabinovich
 * 
 * Use of this source code is governed by a BSD-style
 * license that can be found in the LICENSE file.
 */

namespace CouponCode;

use Exception;

class CouponCode {

    /**
     * The prefix to be added to every CouponCode.
     *
     * @var string
     */
    protected $_prefix = '';

    /**
     * The separator to be used in the CouponCode.
     *
     * @var string
     */
    protected $_separator = '-';

    /**
     * Number of parts of the code.
     *
     * @var integer
     */
    protected $_parts = 2;

    /**
     * Length of each part.
     *
     * @var integer
     */
    protected $_partLength = 4;

    /**
     * Alphabet used when generating codes. Already leaves
     * easy to confuse letters out.
     *
     * @var array
     */
    protected $_symbols = [
        '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K',
        'L', 'M', 'N', 'P', 'Q', 'R', 'T', 'U', 'V', 'W',
        'X', 'Y'
    ];

    /**
     * ROT13 encoded list of bad words.
     *
     * @var array
     */
    protected $_badWords = [
        '0TER', 'AHEQ', 'BTER', 'C00C', 'C0EA', 'CBBC', 'CBEA', 'CEVPX', 'CRAVF', 'CUNG', 'CVFF', 'CVT', 'DHRRE',
        'ENG', 'FA0O', 'FABO', 'FCREZ', 'FUNT', 'FUVG', 'FYHG', 'FYNT', 'G0FF', 'GBFF', 'GHEQ', 'GJNG', 'GVGF',
        'J0EZ', 'JBEZ', 'JNAT', 'JNAX', 'JGS', 'LNX', 'NCR', 'NEFR', 'NFF', 'NVQF', 'O00MR', 'O00O', 'O00OL',
        'O0M0', 'OBBMR', 'OBBO', 'OBBOL', 'OBMB', 'OHGG', 'OHZ', 'ONYYF', 'ORNFG', 'OVGPU', 'P0J', 'P0PX', 'PBJ',
        'PBPX', 'PENC', 'PERRC', 'PHAG', 'PY0JA', 'PYBJA', 'PYVG', 'QRIVY', 'QVPX', 'SERNX', 'SHPX', 'SNEG', 'SNPX',
        'SNG', 'SNGF0', 'SNGFB', 'SRPX', 'TU0FG', 'TUBFG', 'U0Z0', 'UBZB', 'URYY', 'VQV0G', 'VQVBG', 'W0XR', 'W0XRE',
        'W1MM', 'WBXR', 'WBXRE', 'WREX', 'WVFZ', 'WVMM', 'XA0O', 'XABO', 'YVNE', 'ZHSS'
    ];

    /**
     * Constructor.
     *
     * @param array $config Available options are `prefix`, `separator`, `parts` and `partLength`.
     */
    public function __construct(array $config = []) {
        $couponOptionConfig = CouponOption::first();

        $config += [
            'prefix'     => (empty($couponOptionConfig) ? null : $couponOptionConfig->prefix),
            'separator'  => (empty($couponOptionConfig) ? null : $couponOptionConfig->separator),
            'parts'      => (empty($couponOptionConfig) ? null : $couponOptionConfig->parts),
            'partLength' => (empty($couponOptionConfig) ? null : $couponOptionConfig->part_length)
        ];

        if (isset($config['prefix'])) {
            $this->_prefix = $config['prefix'];
        }

        if (isset($config['separator'])) {
            $this->_separator = $config['separator'];
        }

        if (isset($config['parts'])) {
            $this->_parts = $config['parts'];
        }

        if (isset($config['partLength'])) {
            $this->_partLength = $config['partLength'];
        }
    }

    /**
     * Generates a coupon code using the format `XXXX-XXXX` by default.
     *
     * The last character of each part is a checkdigit.
     *
     * Not all letters and numbers are used, so if a person enters the letter 'O' we
     * can automatically correct it to the digit '0'
     * (similarly for I => 1, S => 5, Z => 2).
     *
     * The code generation algorithm avoids 'undesirable' codes. For example any code
     * in which transposed characters happen to result in a valid checkdigit will be
     * skipped.  Any generated part which happens to spell an 'inappropriate' 3-5 letter
     * word (e.g.: 'BUT', 'P00P', 'BOOZE') will also be skipped.
     *
     * @param string $random Allows to directly support a plaintext i.e. for testing.
     * @return string Dash separated and normalized code.
     * @throws Exception
     */
    public function generate($random = null) {
        $results   = [];
        $plaintext = $this->_convert($random ?: $this->_random(8));
        // String is already normalized by used alphabet.
        $part = $try = 0;

        while (count($results) < $this->_parts) {
            $result = substr($plaintext, $try * $this->_partLength, $this->_partLength - 1);

            if (!$result || strlen($result) !== $this->_partLength - 1) {
                throw new Exception('Ran out of plaintext.');
            }

            $result .= $this->_checkdigitAlg1($part + 1, $result);
            $try++;

            if ($this->_isBadWord($result)) {
                continue;
            }

            $part++;
            $results[] = $result;
        }

        if (!empty($this->_prefix)) {

            return $this->_getCodeWithPrefix(implode($this->_separator, $results));
        }

        return implode($this->_separator, $results);
    }


    /**
     * @param int $maxNumberOfCoupons
     * @return array
     */
    public function generateCoupons($maxNumberOfCoupons = 1) {
        $coupons = [];
        for ($i = 0; $i < $maxNumberOfCoupons; $i++) {
            $temp      = $this->generate();
            $coupons[] = $temp;
        }

        return $coupons;
    }

    /**
     * Function to generate new coupons spreadsheet
     *
     * @param int $maxNumberOfCoupons
     * @param null|string $filename
     * @param null|string $sheetname
     * @param bool $addUsedColumn
     */
    public function generateToExcel($maxNumberOfCoupons = 1, $filename = null, $sheetname = null, $addUsedColumn = false) {
        $filename  = (empty(trim($filename)) ? 'coupons' : trim($filename));
        $sheetname = (empty(trim($sheetname)) ? 'coupons' : trim($sheetname));

        $couponsArray = $this->generateCoupons($maxNumberOfCoupons);

        $data = $this->prepareDataForExcel($couponsArray, $addUsedColumn);

        return ExcelHelper::createFromArray($filename, $sheetname, $data)->download(config('constants.extensions.excel'));
    }

    /**
     * Function to export spreadsheet from coupons array
     *
     * @param array $coupons
     * @param null|string $filename
     * @param null|string $sheetname
     * @param bool $addUsedColumn
     */
    static public function couponsToExcel($coupons, $filename = null, $sheetname = null, $addUsedColumn = false) {
        $filename  = (empty(trim($filename)) ? 'coupons' : trim($filename));
        $sheetname = (empty(trim($sheetname)) ? 'coupons' : trim($sheetname));

        $data = (new static)->prepareDataForExcel($coupons, $addUsedColumn);

        return ExcelHelper::createFromArray($filename, $sheetname, $data)->export(config('constants.extensions.excel'));
    }

    /**
     * @param array $body
     * @param bool $addUsedColumn
     * @return array
     */
    private function prepareDataForExcel(array $body, $addUsedColumn = false) {
        $data = [];

        if ($addUsedColumn) {
            $data[0] = [trans('global.COUPON_CODE_TITLE'), trans('global.COUPON_USED_TITLE')]; // the header

            foreach ($body as $value) {
                $isUsed = ($value[1] ? trans('global.YES') : trans('global.NO'));
                $data[] = [$value[0], $isUsed]; // row values
            }
        } else {
            $data[0] = [trans('global.COUPON_CODE_TITLE')]; // the header

            foreach ($body as $value) {
                $data[] = [$value]; // cell value
            }
        }

        return $data;
    }

    /**
     * Validates a given code (or Array of codes). Codes are not case sensitive and
     * certain letters i.e. `O` are converted to digit equivalents
     * i.e. `0`.
     *
     * @param {string|array} $code - String or Array of potentially unnormalized code(s).
     * @return boolean
     */
    public function validate($code) {
        if (is_array($code)) {
            $codes        = $code;
            $isValidArray = [];
            foreach ($codes as $code) {
                $isValidArray[] = $this->validate($code);
            }

            if (in_array(false, $isValidArray, true)) {
                return false;
            }

            return true;
        } else {
            if (substr_count($code, $this->_separator) > ($this->_parts - 1)) {
                //if 'true' there must be a prefix to the coupon, so we'll remove it to validate.
                $couponParts = explode($this->_separator, $code);

                unset($couponParts[0]);// removing the prefix from the coupon to validate
                $code = implode($this->_separator, $couponParts);
            }

            $code = $this->_normalize($code, ['clean' => true, 'case' => true]);

            if (strlen($code) !== ($this->_parts * $this->_partLength)) {
                return false;
            }

            $parts = str_split($code, $this->_partLength);

            foreach ($parts as $number => $part) {
                $expected = substr($part, -1);
                $result   = $this->_checkdigitAlg1($number + 1, $x = substr($part, 0, strlen($part) - 1));
                if ($result !== $expected) {
                    return false;
                }
            }

            return true;
        }
    }

    /**
     * Implements the checkdigit algorithm #1 as used by the original library.
     *
     * @param integer $partNumber Number of the part.
     * @param string $value Actual part without the checkdigit.
     * @return string The checkdigit symbol.
     */
    protected function _checkdigitAlg1($partNumber, $value) {
        $symbolsFlipped = array_flip($this->_symbols);
        $result         = $partNumber;

        foreach (str_split($value) as $char) {
            $result = $result * 19 + $symbolsFlipped[$char];
        }

        return $this->_symbols[$result % (count($this->_symbols) - 1)];
    }

    /**
     * Verifies that a given value is a bad word.
     *
     * @param string $value
     * @return boolean
     */
    protected function _isBadWord($value) {
        return isset($this->_badWords[str_rot13($value)]);
    }

    /**
     * Normalizes a given code (or array of codes) using pre-defined separators.
     *
     * @param {string|array} $string - String or Array of potentially unnormalized string(s).
     * @return string
     */
    public function normalize($string) {
        if (is_array($string)) {
            $strings         = $string;
            $normalizedArray = [];
            foreach ($strings as $string) {
                $normalizedArray[] = $this->normalize($string);
            }

            return $normalizedArray;
        } else {
            if (substr_count($string, $this->_separator) > ($this->_parts - 1)) {
                //if 'true' there must be a prefix to the code, so we'll remove it to validate.
                $couponParts = explode($this->_separator, $string);

                $currentPrefix = $couponParts[0];
                unset($couponParts[0]);// removing the prefix from the coupon to normalize
                $string = implode($this->_separator, $couponParts);

                $string = $this->_normalize($string, ['clean' => true, 'case' => true]);

                return $this->_getCodeWithPrefix(implode($this->_separator, str_split($string, $this->_partLength)), $currentPrefix);
            } else {
                $string = $this->_normalize($string, ['clean' => true, 'case' => true]);
            }

            return implode($this->_separator, str_split($string, $this->_partLength));
        }
    }

    /**
     * Alternative static function to Normalize a given code (or array of codes) using pre-defined separators.
     *
     * @param {string|array} $string - String or Array of potentially unnormalized string(s).
     * @return string
     */
    static public function normalizeStatically($string) {
        $that = (new static);

        if (is_array($string)) {
            $strings         = $string;
            $normalizedArray = [];
            foreach ($strings as $string) {
                $normalizedArray[] = $that->normalize($string);
            }

            return $normalizedArray;
        } else {
            if (substr_count($string, $that->_separator) > ($that->_parts - 1)) {
                //if 'true' there must be a prefix to the code, so we'll remove it to validate.
                $couponParts = explode($that->_separator, $string);

                $currentPrefix = $couponParts[0];
                unset($couponParts[0]);// removing the prefix from the coupon to normalize
                $string = implode($that->_separator, $couponParts);

                $string = $that->_normalize($string, ['clean' => true, 'case' => true]);

                return $that->_getCodeWithPrefix(implode($that->_separator, str_split($string, $that->_partLength)), $currentPrefix);
            } else {
                $string = $that->_normalize($string, ['clean' => true, 'case' => true]);
            }

            return implode($that->_separator, str_split($string, $that->_partLength));
        }
    }

    /**
     * @param array $config
     * @return string
     * @throws Exception
     *
     * @example
     *  $config = [
     *      'prefix' => 'PREFIX', //or null
     *      'separator' => '-',
     *      'parts' => 2,
     *      'partLength' => 6
     *  ];
     *
     *  $code = CouponCode::preview($config);
     *
     *  => returns: 'PREFIX-XXXXXX-XXXXXX';
     */
    static public function preview(array $config = []) {

        $prefix     = (isset($config['prefix']) ? $config['prefix'] : null);
        $separator  = (isset($config['separator']) ? $config['separator'] : null);
        $parts      = (isset($config['parts']) ? $config['parts'] : null);
        $partLength = (isset($config['partLength']) ? $config['partLength'] : null);

        if (empty($separator) || empty($parts) || empty($partLength)) {
            $msg = 'Params: $separator, $parts and $partLength are mandatory and cannot be empty values!';
            throw new Exception($msg);
        }

        $coupon_prefix = (!empty($prefix) ? (strtoupper($prefix) . $separator) : '');
        $code          = '';
        for ($i = 0; $i < $parts; $i++) {
            for ($j = 0; $j < $partLength; $j++) {
                $code .= 'X';
            }
        }

        return $coupon_prefix . implode($separator, str_split($code, $partLength));
    }

    /**
     * Converts givens string using symbols.
     *
     * @param string $string
     * @return string
     */
    protected function _convert($string) {
        $symbols = $this->_symbols;
        $result  = array_map(function ($value) use ($symbols) {
            return $symbols[ord($value) & (count($symbols) - 1)];
        }, str_split(hash('sha1', $string)));

        return implode('', $result);
    }

    /**
     * Internal method to normalize given strings.
     *
     * @param string $string
     * @param array $options
     * @return string
     */
    protected function _normalize($string, array $options = []) {
        $options += [
            'clean' => false,
            'case'  => false
        ];

        if (filter_var($options['case'], FILTER_VALIDATE_BOOLEAN)) {
            $string = strtoupper($string);
        }

        $string = strtr($string, [
            'I' => 1,
            'O' => 0,
            'S' => 5,
            'Z' => 2,
        ]);

        if (filter_var($options['clean'], FILTER_VALIDATE_BOOLEAN)) {
            $string = preg_replace('/[^0-9A-Z]+/', '', $string);
        }

        return $string;
    }

    /**
     * Generates a cryptographically secure sequence of bytes.
     *
     * @param integer $bytes Number of bytes to return.
     * @return string
     * @throws Exception
     */
    protected function _random($bytes) {
        $salt = openssl_random_pseudo_bytes($bytes, $crypto_strong);

        if (!$crypto_strong) {
            if (@is_readable('/dev/urandom')) {
                $stream = fopen('/dev/urandom', 'rb');
                $result = fread($stream, $bytes);
                fclose($stream);

                return $result;
            } else if (function_exists('mcrypt_create_iv')) {
                return mcrypt_create_iv($bytes, MCRYPT_DEV_RANDOM);
            } else {
                // This should not happen
                throw new Exception("No source for generating a cryptographically secure seed found.");
            }
        }

        return $salt;
    }

    /**
     * Generate the Coupon code with the prefix that was set in the config
     * or was passed as a 2nd argument to this function.
     *
     * @param string $code
     * @param null|string $prefix - optional (by default will take the prefix that was set in the config)
     * @return string
     */
    private function _getCodeWithPrefix($code, $prefix = null) {
        if (!empty($prefix)) {
            return strtoupper($prefix) . $this->_separator . $code;
        }

        return strtoupper($this->_prefix) . $this->_separator . $code;
    }
}
?>
