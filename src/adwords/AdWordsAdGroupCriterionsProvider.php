<?php

namespace sitkoru\contextcache\adwords;


use Google\AdsApi\AdWords\AdWordsSession;
use Google\AdsApi\AdWords\v201708\cm\AdGroupCriterion;
use Google\AdsApi\AdWords\v201708\cm\AdGroupCriterionOperation;
use Google\AdsApi\AdWords\v201708\cm\AdGroupCriterionService;
use Google\AdsApi\AdWords\v201708\cm\BiddableAdGroupCriterion;
use Google\AdsApi\AdWords\v201708\cm\Operand;
use Google\AdsApi\AdWords\v201708\cm\Operator;
use Google\AdsApi\AdWords\v201708\cm\Predicate;
use Google\AdsApi\AdWords\v201708\cm\PredicateOperator;
use Google\AdsApi\AdWords\v201708\cm\Selector;
use Google\AdsApi\AdWords\v201708\cm\UserStatus;
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
        'AdGroupId',
        'AgeRangeType',
        'AppId',
        'AppPaymentModelType',
        'ApprovalStatus',
        'BaseAdGroupId',
        'BaseCampaignId',
        'BidModifier',
        'BidType',
        'BiddingStrategyId',
        'BiddingStrategyName',
        'BiddingStrategySource',
        'BiddingStrategyType',
        'CaseValue',
        'ChannelId',
        'ChannelName',
        'CpcBid',
        'CpcBidSource',
        'CpmBid',
        'CpmBidSource',
        'CriteriaCoverage',
        'CriteriaSamples',
        'CriteriaType',
        'CriterionUse',
        'DestinationUrl',
        'DisapprovalReasons',
        'DisplayName',
        'EnhancedCpcEnabled',
        'FinalAppUrls',
        'FinalMobileUrls',
        'FinalUrls',
        'FirstPageCpc',
        'FirstPositionCpc',
        'GenderType',
        'Id',
        'KeywordMatchType',
        'KeywordText',
        'Labels',
        'MobileAppCategoryId',
        'Parameter',
        'ParentCriterionId',
        'ParentType',
        'PartitionType',
        'Path',
        'PlacementUrl',
        'QualityScore',
        'Status',
        'SystemServingStatus',
        'TargetSpendEnhancedCpcEnabled',
        'TopOfPageCpc',
        'TrackingUrlTemplate',
        'UrlCustomParameters',
        'UserInterestId',
        'UserInterestName',
        'UserInterestParentId',
        'UserListEligibleForDisplay',
        'UserListEligibleForSearch',
        'UserListId',
        'UserListMembershipStatus',
        'UserListName',
        'VerticalId',
        'VerticalParentId',
        'VideoId',
        'VideoName'
    ];

    protected $keyField = 'criterion.id';

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
     * @throws \Google\AdsApi\AdWords\v201708\cm\ApiException
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
            foreach (array_chunk($ids, self::MAX_RESPONSE_COUNT) as $idsChunk) {
                $selector = new Selector();
                $selector->setFields(self::$fields);
                $predicates[] = new Predicate('Id', PredicateOperator::IN, $idsChunk);
                $selector->setPredicates($predicates);
                $fromService = (array)$this->adGroupCriterionService->get($selector)->getEntries();
                foreach ($fromService as $criterionItem) {
                    $index = $indexBy($criterionItem);
                    $criterions[$index] = $criterionItem;
                }
                $this->addToCache($fromService);
            }
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
     * @throws \Google\AdsApi\AdWords\v201708\cm\ApiException
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
     * @param array $campaignIds
     * @return AdGroupCriterion[]
     * @throws \Google\AdsApi\AdWords\v201708\cm\ApiException
     */
    public function getByCampaignIds(array $campaignIds): array
    {
        $notFound = $campaignIds;

        $indexBy = function (AdGroupCriterion $criterion) {
            return $criterion->getBaseCampaignId() . $criterion->getCriterion()->getId();
        };
        /**
         * @var AdGroupCriterion[] $criterions
         */
        $criterions = $this->getFromCache($campaignIds, 'campaignId', $indexBy);
        if ($criterions) {
            $found = array_unique(ArrayHelper::getColumn($criterions, 'campaignId'));
            $notFound = array_values(array_diff($campaignIds, $found));
        }
        if ($notFound) {
            $selector = new Selector();
            $selector->setFields(self::$fields);
            $predicates[] = new Predicate('CampaignId', PredicateOperator::IN, $notFound);
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
        $result = new UpdateResult();
        $deleteOperations = [];
        $addOperations = [];
        $this->logger->info('Build operations');
        foreach ($entities as $entity) {
            $newCriterion = clone $entity;
            $criterion = clone $entity->getCriterion();
            if ($newCriterion instanceof BiddableAdGroupCriterion && $newCriterion->getBiddingStrategyConfiguration() !== null) {
                $newCriterion->setBiddingStrategyConfiguration(null);
            }
            $entity->setUserStatus(UserStatus::REMOVED);
            $deleteOperation = new AdGroupCriterionOperation();
            $deleteOperation->setOperand($entity);
            $deleteOperation->setOperator(Operator::REMOVE);
            $criterion->setId(null);
            $newCriterion->setCriterion($criterion);
            $addOperation = new AdGroupCriterionOperation();
            $addOperation->setOperand($newCriterion);
            $addOperation->setOperator(Operator::ADD);
            $deleteOperations[$entity->getCriterion()->getId()] = $deleteOperation;
            $addOperations[] = $addOperation;
        }
        $this->logger->info('Delete operations: ' . count($deleteOperations));
        $this->logger->info('Update operations: ' . count($addOperations));


        foreach (array_chunk($addOperations, self::MAX_OPERATIONS_SIZE) as $i => $addChunk) {
            /**
             * @var AdGroupCriterionOperation[] $addChunk
             */
            $this->logger->info('Add chunk #' . $i . '. Size: ' . count($addChunk));
            $jobResults = $this->runMutateJob($addChunk);
            $this->processJobResult($result, $jobResults);
            if (!$result->success) {
                foreach ($result->errors as $criterionOperationId => $errors) {
                    $criterionOperation = $addChunk[$criterionOperationId];
                    $criterionId = $criterionOperation->getOperand()->getCriterion()->getId();
                    unset($deleteOperations[$criterionId]);
                }
            }
        }

        $this->logger->info('Delete succeeded criterions: ' . count($deleteOperations));
        foreach (array_chunk($deleteOperations, self::MAX_OPERATIONS_SIZE) as $i => $deleteChunk) {
            $this->logger->info('Delete chunk #' . $i . '. Size: ' . count($deleteChunk));
            $jobResults = $this->runMutateJob($deleteChunk);
            $this->processJobResult($result, $jobResults);
        }

        $this->logger->info('Done');
        $this->clearCache();
        return $result;
    }

    protected function getOperandEntity(Operand $operand)
    {
        return $operand->getAdGroupCriterion();
    }
}