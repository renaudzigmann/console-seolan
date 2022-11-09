<?php
/**
 * Password Generator
 *
 * LICENSE
 *
 * This source file is licensed under the Creative Commons Attribution
 * 2.5 License that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * {@link http://creativecommons.org/licenses/by/2.5/}
 *
 * @category   Password Utilities
 * @package    Password
 * @author     Aleksey V. Zapparov A.K.A. iXTi <ixti.ru@gmail.com>
 * @license    http://creativecommons.org/licenses/by/2.5/
 */

/**
 * Password generating class
 *
 * @category   Password Utilities
 * @package    Password
 * @author     Aleksey V. Zapparov A.K.A. iXTi <ixti.ru@gmail.com>
 * @license    http://creativecommons.org/licenses/by/2.5/
 */
class Password
{
    /**
     * Used in Password::generate(), as third option.
     * Set letters in generated password(s) to lower register.
     *
     * @see Password::generate()
     */
    const LOWER  = -1;
    
    
    /**
     * Used in Password::generate(), as third option.
     * Set letters in generated password(s) to random register.
     *
     * @see Password::generate()
     */
    const RANDOM = 0;
    
    
    /**
     * Used in Password::generate(), as third option.
     * Set letters in generated password(s) to upper register.
     *
     * @see Password::generate()
     */
    const UPPER  = 1;
    
    
    /**
     * Singletone pattern
     *
     * @var object Password
     */
    static private $_instance;
    
    
    /**
     * Alphabet two-dimension array. 
     *
     * @see Password::setAlphabet()
     * @var array $_alphabet
     */
    private $_alphabet = array();
    
    
    /**
     * Dictionary array
     *
     * @see Password::setDictionary()
     * @var array $_dictionary
     */
    private $_dictionary = array();
    
    
    /**
     * Singletone pattern
     */
    private function __construct()
    {
        $this->setAlphabet();
    }
    
    
    /**
     * Singletone pattern
     */
    private function __clone()
    {}
    
    
    /**
     * Singletone pattern
     *
     * @return object Password
     */
    static public function getInstance()
    {
        if (!self::$_instance instanceof self)
        {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    
    /**
     * Tells Password class to use password dictionary when creating passwords.
     * 
     * If $dictionary is a string, then it tries to open file with a specified
     * name. Each line of that file will be treaten as a sample password.
     * If specified file cannot be opened then Exception thowed.
     * 
     * If $dictionary is an array then each element of it will be treaten as a
     * sample password.
     * 
     * To unset ditionary, call setDictionary() without $dictionary specified,
     * or specify it as array().
     *
     * @throws  Exception If password dictionary cannot being found
     * @param   string|array $dictionary (optional) Dictionary to use
     * @return  object Password
     */
    public function setDictionary($dictionary = array())
    {
        if (!is_array($dictionary)) {
            $dictionary = @file($dictionary);
            if (!$dictionary) {
                throw new \Exception('Password dictionary file not found!');
            }
        }
        
        $this->_dictionary = array_values($dictionary);
        return $this;
    }
    
    
    /**
     * Set custom alphabet. This can be usefull for creating easy-pronouncing
     * passwords without using of dictionary, for example:
     * 
     * <code>
     * $alphabetEven = array('b', 'd', 'f', 'g', 'h', 'k', 'l', 'm', 'n', 'p',
     *                       'r', 's', 't', 'v', 'w', 'z');
     * $alphabetOdd  = array('a', 'e', 'i', 'o', 'u', 'y');
     * </code>
     * 
     * So when you'll be generating new password without dictionary (or even
     * with dictionary) letters will be chosen from one then from another array.
     * 
     * To clearly understand why, here you some sources of Password::_generate()
     * method:
     * 
     * <code>
     * for ($char_id = count($password); $char_id < array_sum($passLength);
     *      $char_id++) {
     *     $chars_array = $this-{>}_alphabet[$char_id % 2];
     *     $password[]  = $chars_array[mt_rand(0, count($chars_array) - 1)];
     * }
     * </code>
     * 
     * If $alphabetEven is not specified, or an empty array is given, then it
     * will be treaten as range('a', 'z')
     * 
     * If $alphabetOdd is not specified, or an empty array is given, then it
     * will be equal to $alphabetEven
     * 
     * $alphabetEven and $alphabetOdd must be a single dimenson numeric arrays
     * with single char as values. Valid examples:
     * 
     * <code>
     * Password::getInstance()->setAlphabet(range('a', 'd'));
     * Password::getInstance()->setAlphabet(range('a', 'd'), range('e', 'z'));
     * Password::getInstance()->setAlphabet(array('a', 'b', 'c', 'd'));
     * Password::getInstance()->setAlphabet(array('a', 'b', 'c', 'd'),
     *                                      array('e', 'f', 'g'));
     * </code>
     * 
     * Note that if you'll specify both $alphabetEven and $alphabetOdd as
     * arrays with only one value, then you'll get very-very unsecure password.
     * IMHO it will be anything but not a password:
     * 
     * <code>
     * Password::getInstance()->setAlphabet(array('a'))
     *                        ->generate();
     *                        // Result is: 6aaAaa3A (and so on)
     * Password::getInstance()->setAlphabet(array('a'), array('b'))
     *                        ->generate();
     *                        // Result is: abA48bAb (and so on)
     * Password::getInstance()->setAlphabet(array('a'))
     *                        ->generate(array(8,0));
     *                        // Result is: aaaaaaaa
     * </code>
     *
     * @throws  Exception If $alphabetEven or $alphabetOdd is invalid.
     * @param   array   $alphabetEven (optional)
     * @param   array   $alphabetOdd  (optional)
     * @return  object Password
     */
    public function setAlphabet(array $alphabetEven = array(),
                                array $alphabetOdd = array())
    {
        // Check validity of $alphabetEven
        if (0 === count($alphabetEven)) {
            $alphabetEven = range('a', 'z');
        } else {
            $alphabetEven = array_values($alphabetEven);
            for ($char_id = 0; $char_id < count($alphabetEven); $char_id++) {
                if (1 != strlen($alphabetEven[$char_id])) {
                    $error = sprintf('$alphabetEven is not valid. Only single '
                                     . 'dimension numeric arrays with single '
                                     . 'char as values allowed. There is at '
                                     . 'least one value that is not single '
                                     . 'char. $alphabetEven[%d] = "%s".',
                                     $char_id, $alphabetEven[$char_id]);
                    throw new \Exception($error);
                }
            }
        }
        
        // Check validity of $alphabetOdd
        if (0 === count($alphabetOdd)) {
            $alphabetOdd = $alphabetEven;
        } else {
            $alphabetOdd = array_values($alphabetOdd);
            for ($char_id = 0; $char_id < count($alphabetOdd); $char_id++) {
                if (1 != strlen($alphabetOdd[$char_id])) {
                    $error = sprintf('$alphabetOdd is not valid. Only single '
                                     . 'dimension numeric arrays with single '
                                     . 'char as values allowed. There is at '
                                     . 'least one value that is not single '
                                     . 'char. $alphabetOdd[%d] = "%s".',
                                     $char_id, $alphabetOdd[$char_id]);
                    throw new \Exception($error);
                }
            }
        }
        
        // Set alphabet and return $this object
        $this->_alphabet = array($alphabetEven, $alphabetOdd);
        return $this;
    }
    
    
    /**
     * Generate password(s)
     * 
     * The default representation of $passLength is 'array => (letters, digits)'
     * but it can be given as an integer. If so, then it will be converted into
     * array with letters = 75% of $passLength and left 25% as digits.
     * Default is 6 letters and 2 digits. Valid examples are:
     * 
     * <code>
     * $passLength = array(16, 2); // letters:16, digits:2, total length:18
     * $passLength = array(6, 0);  // letters:6,  digits:0, total length:6
     * $passLength = '15';         // letters:11, digits:4, total length:15
     * $passLength = '9';          // letters:7,  digits:2, total length:9
     * </code>
     * 
     * If $amountOfPasswords is more then one, then array with passwords of
     * $passLength and $lettersCase will be returned.
     * 
     * $lettersCase let you choose which letter case to use in generated
     * password(s). Available values are: Password::LOWER, Password::UPPER and
     * Password::RANDOM. Password::RANDOM is default option.
     *
     * @uses   Password::_generate()
     * @param  array|integer $passLength (optional) Generated password's length
     * @param  integer       $amountOfPasswords (optional) Amount of passwords
     * @param  integer       $lettersCase (optional) Letter's case in passwords
     * @return string|array  Generated password(s)
     */
    public function generate($passLength = array(6, 2), $amountOfPasswords = 1,
                             $lettersCase = self::RANDOM)
    {
        // Getting amount of letters and digits in password which must be
        // present in generated password(s). Doing the same operation as in
        // $this->_generate, just to prevent of conbverting from 
        if (!is_array($passLength)) {
            $letters     = round($passLength*0.75);
            $pass_length = array($letters, $passLength-$letters);
        } else {
            $pass_length = array_values($passLength);
            if (0 == count($pass_length)) {
                $pass_length = array(6, 2);
            } elseif (1 == count($pass_length)) {
                $pass_length = array($pass_length[0], 0);
            } else {
                $pass_length = array($pass_length[0], $pass_length[1]);
            }
        }
        
        // If specified $amountOfPasswords greater then 1, then create an array
        // with generated passwords, else simply generate one password.
        if ($amountOfPasswords > 1) {
            $password = array();
            for ($pass_id = 0; $pass_id < $amountOfPasswords; $pass_id++) {
                $password[$pass_id] = $this->_generate($pass_length,
                                                       $lettersCase);
            }
        } else {
            $password = $this->_generate($pass_length, $lettersCase);
        }
        
        return $password;
    }
    
    
    /**
     * Main method that generates a single password.
     *
     * @param   array   $passLength (optional) Generated password's length
     * @param   integer $lettersCase (optional) Letters case in passwords
     * @return  string
     */
    final private function _generate(array $passLength = array(6, 2),
                                     $lettersCase = self::RANDOM)
    {
        $password = array();
        
        // If password's dictionary not empty array then use it
        if (0 != count($this->_dictionary)) {
            // Take random password from password's dictionary array
            $password = $this->_dictionary[array_rand($this->_dictionary)];
            // Remove all 'new lines' and 'carriage returns' from password
            // and explode it into character's array
            $password = str_split(preg_replace("/[\r\n]+/", '',  $password));
        }
        
        // If $password generated from dictionary is grater then maximum
        // password length, then trim it to maximum (amount of letters + 
        // amount of digits)
        if (count($password) > array_sum($passLength)) {
            $password = array_splice($password, 0, array_sum($passLength));
        }
        
        // Fill $password with random letters from $this->_alphabet array
        for ($char_id = count($password); $char_id < array_sum($passLength);
             $char_id++) {
            $chars_array = $this->_alphabet[$char_id % 2];
            $password[]  = $chars_array[mt_rand(0, count($chars_array) - 1)];
        }
        
        // Replace some letters with specified in $passLength amount of digits
        for ($digit_num = 0; $digit_num < $passLength[1]; $digit_num++) {
            $char_id = mt_rand(0, array_sum($passLength) - 1);
            while (is_numeric($password[$char_id])) {
                $char_id++;
                if ($char_id > array_sum($passLength) - 1) {
                    $char_id = 0;
                }
            }
            $password[$char_id] = mt_rand(0, 9);
        }
        
        // Change password letter's case to one specified in $lettersCase
        for ($char_id = 0; $char_id < count($password); $char_id++) {
            switch ($lettersCase) {
                case self::LOWER :
                    $password[$char_id] = strtolower($password[$char_id]);
                    break;
                    
                case self::UPPER :
                    $password[$char_id] = strtoupper($password[$char_id]);
                    break;
                    
                default:
                    $password[$char_id] = (mt_rand(0, 1))
                                        ? strtolower($password[$char_id])
                                        : strtoupper($password[$char_id]);
            }
        }
        
        return implode('', $password);
    }
}