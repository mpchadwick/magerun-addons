<?php

namespace Mpchadwick\Magento\Command\Dev\Search;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Faker;

class SeedCommand extends AbstractMagentoCommand
{
    /** Number of seeds INSERTed in each query */
    const INSERT_CHUNK_SIZE = 10000;

    /** The minimum number of results for a query */
    const MIN_RESULTS = 0;

    /** The maximum number of results for a query */
    const MAX_RESULTS = 100;

    /** The minimum number popularity setting */
    const MIN_POPULARITY = 1;

    /** The maximum popularity number */
    const MAX_POPULARITY = 1000000;

    /** Likelyhood that a given term will be a direct */
    const REDIRECT_PROBABILITY = 0.02;

    /** Likelyhood that a given term will be a synonym */
    const SYNONYM_PROBABILITY = 0.02;

    /** @var InputInterface $input */
    protected $input;

    /** @var OutputInterface $input */
    protected $output;

    /** @var Faker $faker */
    protected $faker;

    protected $popularityRange = [];

    protected function configure()
    {
        $this
            ->setName('dev:search:seed')
            ->setDescription('Seed the catalogsearch_query table')
            ->addArgument('count', InputOption::VALUE_REQUIRED, 'Count');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->faker = Faker\Factory::create();

        $resource = \Mage::getSingleton('core/resource');
        $writeConnection = $resource->getConnection('core_write');
        $table = $resource->getTableName('catalogsearch_query');
        $now = date('Y-m-d H:i:s');

        $this->detectMagento($output, true);
        $this->initMagento();

        $records = [];

        for ($i = 0; $i < $this->input->getArgument('count'); $i++) {
            $records[] = [
                'query_text' => $this->faker->word,
                'num_results' => rand(self::MIN_RESULTS, self::MAX_RESULTS),
                'popularity' => $this->popularity(),
                'redirect'  => $this->probabilityBasedValue('redirect'),
                'synonym_for' => $this->probabilityBasedValue('synonym_for'),
                'updated_at' => $now,
            ];

            if (count($records) >= self::INSERT_CHUNK_SIZE) {
                $writeConnection->insertMultiple($table, $records);
                $records = [];
            }
        }

        if (!empty($records)) {
            $writeConnection->insertMultiple($table, $records);
        }
    }

    protected function popularity()
    {
        // Long tail distribution with some really high volume search terms
        $temp = rand(1, 1000);
        if ($temp === 1) {
            return rand(self::MAX_POPULARITY * 0.1, self::MAX_POPULARITY);
        } elseif ($temp <= 11) {
            return rand(self::MAX_POPULARITY * 0.01, self::MAX_POPULARITY * 0.09);
        } elseif ($temp <= 100) {
            return rand(self::MAX_POPULARITY * 0.001, self::MAX_POPULARITY * 0.009);
        } else {
            return rand(self::MIN_POPULARITY, self::MAX_POPULARITY * 0.0009);
        }
    }

    protected function probabilityBasedValue($type)
    {
        $probability = 0;
        $fakerProp = null;

        switch ($type) {
            case 'redirect':
                $probability = self::REDIRECT_PROBABILITY;
                $fakerProp = 'url';
                break;
            case 'synonym_for':
                $probability = self::SYNONYM_PROBABILITY;
                $fakerProp = 'word';
                break;
            default:
                break;
        }

        if (rand(0, 100) > $probability * 100) {
            return null;
        }

        return $this->faker->$fakerProp;
    }
}
