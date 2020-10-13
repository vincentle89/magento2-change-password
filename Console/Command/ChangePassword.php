<?php

namespace Vincentle89\ChangePassword\Console\Command;

use Symfony\Component\Console\Command\Command;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\Stdlib\StringUtils as StringHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State as AppState;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Magento\Customer\Model\Customer;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Console\Cli;
use Magento\Customer\Model\AccountManagement;
use Magento\Framework\Exception\InputException;

/**
 * Class ChangePassword
 * @package Vincentle89\ChangePassword\Console\Command
 */
class ChangePassword extends Command
{

    /**#@+
     * Data keys
     */
    const KEY_CUSTOMER_ID = 'customer-id';
    const KEY_CUSTOMER_PASSWORD = 'customer-password';

    /**
     * @var CustomerRepositoryInterface
     */
    private $_customerRepository;

    /**
     * @var StringHelper
     */
    private $_stringHelper;

    /**
     * @var ScopeConfigInterface
     */
    private $_scopeConfig;

    /**
     * @var AppState
     */
    private $_appState;

    /**
     * @var CustomerRegistry
     */
    protected $_customerRegistry;

    /**
     * @var EncryptorInterface
     */
    protected $_encryptor;

    /**
     * ChangePassword constructor.
     * @param CustomerRepositoryInterface $customerRepository
     * @param StringHelper $stringHelper
     * @param ScopeConfigInterface $scopeConfig
     * @param AppState $appState
     * @param CustomerRegistry $customerRegistry
     * @param EncryptorInterface $encryptor
     * @param null $name
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        StringHelper $stringHelper,
        ScopeConfigInterface $scopeConfig,
        AppState $appState,
        CustomerRegistry $customerRegistry,
        EncryptorInterface $encryptor,
        $name = null
    )
    {
        parent::__construct($name);
        $this->_appState = $appState;
        $this->_scopeConfig = $scopeConfig;
        $this->_stringHelper = $stringHelper;
        $this->_customerRepository = $customerRepository;
        $this->_customerRegistry = $customerRegistry;
        $this->_encryptor = $encryptor;
    }

    /**
     * Initialization of the command
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('customer:changepassword')
            ->setDescription('Change customer password')
            ->setDefinition($this->getOptionsList());
        parent::configure();
    }

    /**
     * Get list of arguments for the command
     *
     * @return InputOption[]
     */
    public function getOptionsList()
    {
        return [
            new InputOption(self::KEY_CUSTOMER_ID, null, InputOption::VALUE_REQUIRED, '(Required) Customer ID'),
            new InputOption(self::KEY_CUSTOMER_PASSWORD, null, InputOption::VALUE_REQUIRED, '(Required) Customer password')
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $errors = $this->validate($input);
        if ($errors) {
            $output->writeln('<error>' . implode('</error>' . PHP_EOL . '<error>', $errors) . '</error>');
            // we must have an exit code higher than zero to indicate something was wrong
            return Cli::RETURN_FAILURE;
        }
        $customer = $this->_customerRepository->getById($input->getOption(self::KEY_CUSTOMER_ID));
        $this->_appState->setAreaCode('frontend');
        $customerSecure = $this->_customerRegistry->retrieveSecureData($input->getOption(self::KEY_CUSTOMER_ID)); // _customerRegistry is an instance of \Magento\Customer\Model\CustomerRegistry
        $customerSecure->setRpToken(null);
        $customerSecure->setRpTokenCreatedAt(null);
        $customerSecure->setPasswordHash($this->_encryptor->getHash($input->getOption(self::KEY_CUSTOMER_PASSWORD), true)); // here _encryptor is an instance of \Magento\Framework\Encryption\EncryptorInterface
        $this->_customerRepository->save($customer);

        $output->writeln(
            '<info>Password for customer #' . $input->getOption(self::KEY_CUSTOMER_ID) . ' has been successfully changed</info>'
        );
    }

    /**
     * Check if all admin options are provided
     *
     * @param InputInterface $input
     * @return string[]
     */
    public function validate(InputInterface $input)
    {
        $errors = [];

        try {
            $this->checkPasswordStrength($input->getOption(self::KEY_CUSTOMER_PASSWORD));
            /** @var Customer $customer */
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }

        return $errors;
    }

    /**
     * Make sure that password complies with minimum security requirements.
     *
     * @param string $password
     * @throws InputException
     */
    protected function checkPasswordStrength($password)
    {
        $length = $this->_stringHelper->strlen($password);
        if ($length > AccountManagement::MAX_PASSWORD_LENGTH) {
            throw new InputException(
                __(
                    'Please enter a password with at most %1 characters.',
                    AccountManagement::MAX_PASSWORD_LENGTH
                )
            );
        }
        $configMinPasswordLength = $this->getMinPasswordLength();
        if ($length < $configMinPasswordLength) {
            throw new InputException(
                __(
                    'Please enter a password with at least %1 characters.',
                    $configMinPasswordLength
                )
            );
        }
        if ($this->_stringHelper->strlen(trim($password)) != $length) {
            throw new InputException(__('The password can\'t begin or end with a space.'));
        }

        $requiredCharactersCheck = $this->makeRequiredCharactersCheck($password);
        if ($requiredCharactersCheck !== 0) {
            throw new InputException(
                __(
                    'Minimum of different classes of characters in password is %1.' .
                    ' Classes of characters: Lower Case, Upper Case, Digits, Special Characters.',
                    $requiredCharactersCheck
                )
            );
        }
    }

    /**
     * Retrieve minimum password length
     *
     * @return int
     */
    protected function getMinPasswordLength()
    {
        return $this->_scopeConfig->getValue(AccountManagement::XML_PATH_MINIMUM_PASSWORD_LENGTH);
    }

    /**
     * Check password for presence of required character sets
     *
     * @param string $password
     * @return int
     */
    protected function makeRequiredCharactersCheck($password)
    {
        $counter = 0;
        $requiredNumber = $this->_scopeConfig->getValue(AccountManagement::XML_PATH_REQUIRED_CHARACTER_CLASSES_NUMBER);
        $return = 0;

        if (preg_match('/[0-9]+/', $password)) {
            $counter++;
        }
        if (preg_match('/[A-Z]+/', $password)) {
            $counter++;
        }
        if (preg_match('/[a-z]+/', $password)) {
            $counter++;
        }
        if (preg_match('/[^a-zA-Z0-9]+/', $password)) {
            $counter++;
        }

        if ($counter < $requiredNumber) {
            $return = $requiredNumber;
        }

        return $return;
    }
}