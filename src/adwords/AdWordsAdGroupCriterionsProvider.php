<?php

namespace sitkoru\contextcache\adwords;


use Google\AdsApi\AdWords\AdWordsSession;
use Google\AdsApi\AdWords\v201702\cm\AdGroupCriterion;
use Google\AdsApi\AdWords\v201702\cm\AdGroupCriterionService;
use Google\AdsApi\AdWords\v201702\cm\Predicate;
use Google\AdsApi\AdWords\v201702\cm\PredicateOperator;
use Google\AdsApi\AdWords\v201702\cm\Selector;
use Psr\Log\LoggerInterface;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\IEntitiesProvider;
use sitkoru\contextcache\common\models\UpdateResult;
use sitkoru\contextcache\helpers\ArrayHelper;

class AdWordsAdGroupCriterionsProvider extends AdWordsEntitiesProvider implements IEntitiesProvider
{

    /**
     * @var AdGroupCriterionService
     */
    private $adGroupCriterionService;

    private static $fields = [
        'KeywordText',
        'KeywordMatchType',
        'Id',
        'FirstPageCpc',
        'BiddingStrategyType',
        'CpcBid'
    ];

    public function __construct(
        AdGroupCriterionService $adGroupCriterionService,
        ICacheProvider $cacheProvider,
        AdWordsSession $adWordsSession,
        LoggerInterface $logger
    ) {
        parent::__construct($cacheProvider, $adWordsSession, $logger);
        $this->collection = 'adGroupCriterions';
        $this->adGroupCriterionService = $adGroupCriterionService;
    }

    /**
     * @param array $ids
     * @return AdGroupCriterion[]
     */
    public function getAll(array $ids): array
    {
        $notFound = $ids;

        $indexBy = function (AdGroupCriterion $criterion) {
            return $criterion->getAdGroupId() . $criterion->getCriterion()->getId();
        };
        /**
         * @var AdGroupCriterion[] $criterions
         */
        $criterions = $this->getFromCache($ids, 'criterion.id', $indexBy);
        if ($criterions) {
            $found = array_unique(ArrayHelper::getColumn($criterions, function (AdGroupCriterion $criterion) {
                return $criterion->getCriterion()->getId();
            }));
            $notFound = array_values(array_diff($ids, $found));
        }
        if ($notFound) {
            $selector = new Selector();
            $selector->setFields(self::$fields);
            $predicates[] = new Predicate('Id', PredicateOperator::IN, $ids);
            $selector->setPredicates($predicates);
            $fromService = (array)$this->adGroupCriterionService->get($selector)->getEntries();
            foreach ($fromService as $criterionItem) {
                $index = $indexBy($criterionItem);
                $criterions[$index] = $criterionItem;
            }
            $this->addToCache($fromService);
        }
        return $criterions;
    }

    public function getOne($id): AdGroupCriterion
    {
        $criterions = $this->getAll([$id]);
        if ($criterions) {
            return reset($criterions);
        }
        return null;
    }

    /**
     * @param array $adGroupIds
     * @return AdGroupCriterion[]
     */
    public function getByAdGroupIds(array $adGroupIds): array
    {
        $notFound = $adGroupIds;

        $indexBy = function (AdGroupCriterion $criterion) {
            return $criterion->getAdGroupId() . $criterion->getCriterion()->getId();
        };
        /**
         * @var AdGroupCriterion[] $criterions
         */
        $criterions = $this->getFromCache($adGroupIds, 'adGroupId', $indexBy);
        if ($criterions) {
            $found = array_unique(ArrayHelper::getColumn($criterions, 'adGroupId'));
            $notFound = array_values(array_diff($adGroupIds, $found));
        }
        if ($notFound) {
            $selector = new Selector();
            $selector->setFields(self::$fields);
            $predicates[] = new Predicate('AdGroupId', PredicateOperator::IN, $notFound);
            $selector->setPredicates($predicates);
            $fromService = (array)$this->adGroupCriterionService->get($selector)->getEntries();
            foreach ($fromService as $criterionItem) {
                $index = $indexBy($criterionItem);
                $criterions[$index] = $criterionItem;
            }
            $this->addToCache($fromService);
        }
        return $criterions;
    }

    /**
     * @param AdGroupCriterion[] $entities
     * @return UpdateResult
     * @throws \Exception
     */
    public function update(array $entities): UpdateResult
    {
        return new UpdateResult();
    }
}