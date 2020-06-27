<?php
/**
 * @author      Oleh Kravets <oleh.kravets@snk.de>
 * @copyright   Copyright (c) 2020 schoene neue kinder GmbH  (https://www.snk.de)
 * @license     https://opensource.org/licenses/MIT  The MIT License (MIT)
 */
namespace Snk\TaxUpdater\Console;

use Magento\Framework\{
    Api\SearchCriteriaBuilder,
    Api\SearchCriteriaBuilderFactory,
    Exception\InputException,
    Exception\NoSuchEntityException
};
use Magento\Tax\{
    Api\Data\TaxRateInterface,
    Api\TaxRateRepositoryInterface
};
use Symfony\Component\{
    Console\Command\Command,
    Console\Input\InputInterface,
    Console\Input\InputOption,
    Console\Output\OutputInterface
};

class UpdateTaxRateCommand extends Command
{
    const OPTION_ID = 'id';
    const OPTION_COUNTRY_CODE = 'country';
    const OPTION_OLD_RATE = 'old-rate';
    const OPTION_NEW_RATE = 'new-rate';
    const OPTION_DRY_RUN = 'dry-run';

    /**
     * @var TaxRateRepositoryInterface
     */
    private $taxRateRepository;
    /**
     * @var SearchCriteriaBuilderFactory
     */
    private $criteriaBuilderFactory;

    public function __construct(
        TaxRateRepositoryInterface $taxRateRepository,
        SearchCriteriaBuilderFactory  $criteriaBuilderFactory
    ) {
        parent::__construct();
        $this->taxRateRepository = $taxRateRepository;
        $this->criteriaBuilderFactory = $criteriaBuilderFactory;
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this
            ->setName('tax:rate:update')
            ->setDescription('Updates the rate of existing tax rates.')
            ->addOption(
                self::OPTION_ID,
                null,
                InputOption::VALUE_OPTIONAL,
                'Tax rate ID'
            )
            ->addOption(
                self::OPTION_COUNTRY_CODE,
                null,
                InputOption::VALUE_OPTIONAL,
                'Tax rate country code.'
            )
            ->addOption(
                self::OPTION_NEW_RATE,
                null,
                InputOption::VALUE_REQUIRED,
                'New tax rate.'
            )
            ->addOption(
                self::OPTION_OLD_RATE,
                null,
                InputOption::VALUE_OPTIONAL,
                'Old tax rate.'
            )
            ->addOption(
                self::OPTION_DRY_RUN,
                null,
                InputOption::VALUE_OPTIONAL,
                'Dry run: do not save the tax rate.'
            )
        ;
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $taxRateId   = $input->getOption(self::OPTION_ID);
        $newRate = (float) $input->getOption(self::OPTION_NEW_RATE);

        if (!$newRate) {
            $output->writeln('<error>New tax rate must be specified</error>');
            return 1;
        }

        if ($taxRateId) {
            try {
                $taxRate = $this->taxRateRepository->get($taxRateId);
                $this->saveNewRate($output, $taxRate, $newRate);
            } catch (NoSuchEntityException $exception) {
                $output->writeln(sprintf('<warning>No Tax Rate with ID %s found </warning>', $taxRateId));
                return 1;
            }
        } else {
            $countryCode = $input->getOption(self::OPTION_COUNTRY_CODE);
            $oldRate = (float) $input->getOption(self::OPTION_OLD_RATE);

            if (!($countryCode && $oldRate)) {
                $output->writeln('<error>Tax rate ID or either country code and rate must be specified</error>');
                return 1;
            }

            $taxRates = $this->getRatesByCountryAndRate($countryCode, $oldRate);

            if (count($taxRates)) {
                foreach ($taxRates as $taxRate) {
                    $this->saveNewRate($output, $taxRate, $newRate);
                }
            } else {
                $output->writeln(sprintf(
                    '<comment>No tax rates for %s with rate %g%% have been found</comment>',
                    $countryCode,
                    $oldRate
                ));
            }
        }

        if ($input->getOption(self::OPTION_DRY_RUN)) {
            $output->writeln('<comment>Dry Run Mode. No data has been modified</comment>');
        }

        return 0;
    }

    /**
     * @param OutputInterface $output
     * @param TaxRateInterface $taxRate
     * @param $newRate
     * @param bool $dryRun
     * @return void
     */
    private function saveNewRate(OutputInterface $output, TaxRateInterface $taxRate, $newRate, $dryRun = false)
    {
        // try to update tax code. should work wor most simple cases
        $taxRate->setCode(str_replace(
            sprintf('%g%%', $taxRate->getRate()),
            sprintf('%g%%', $newRate),
            $taxRate->getCode()
        ));

        $taxRate->setRate($newRate);

        if (!$dryRun) {
            try {
                $this->taxRateRepository->save($taxRate);
            } catch (\Exception $exception) {
                $output->writeln(sprintf(
                    '<error>Cannot save Tax Rate with ID %d. Error message: %s</error>',
                    $taxRate->getId(),
                    $exception->getMessage()
                ));
            }
        }

        $output->writeln(sprintf(
            '<info>Tax Rate with ID %d for country %s has been saved  with new percent rate %g%%</info>',
            $taxRate->getId(),
            $taxRate->getTaxCountryId(),
            $taxRate->getRate()
        ));
    }

    /**
     * @param string $countryCode
     * @param float $oldRate
     * @return TaxRateInterface[]
     * @throws InputException
     */
    private function getRatesByCountryAndRate($countryCode, float $oldRate)
    {
        /** @var SearchCriteriaBuilder $criteriaBuilder */
        $criteriaBuilder = $this->criteriaBuilderFactory->create();
        if ($countryCode) {
            $criteriaBuilder->addFilter('tax_country_id', $countryCode);
        }

        if ($oldRate) {
            $criteriaBuilder->addFilter('rate', $oldRate);
        }

        return $this->taxRateRepository->getList($criteriaBuilder->create())->getItems();
    }
}
