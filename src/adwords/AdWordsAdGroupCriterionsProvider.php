<?php

namespace sitkoru\contextcache\adwords;


use Google\AdsApi\AdWords\AdWordsSession;
use Google\AdsApi\AdWords\v201802\cm\AdGroupCriterion;
use Google\AdsApi\AdWords\v201802\cm\AdGroupCriterionOperation;
use Google\AdsApi\AdWords\v201802\cm\AdGroupCriterionService;
use Google\AdsApi\AdWords\v201802\cm\Operand;
use Google\AdsApi\AdWords\v201802\cm\Operator;
use Google\AdsApi\AdWords\v201802\cm\Paging;
use Google\AdsApi\AdWords\v201802\cm\Predicate;
use Google\AdsApi\AdWords\v201802\cm\PredicateOperator;
use Google\AdsApi\AdWords\v201802\cm\Selector;
use sitkoru\contextcache\common\ContextEntitiesLogger;
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

    /**
     * @var array
     */
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

    public function __construct(
        AdGroupCriterionService $adGroupCriterionService,
        ICacheProvider $cacheProvider,
        AdWordsSession $adWordsSession,
        ContextEntitiesLogger $logger
    ) {
        parent::__construct($cacheProvider, $adWordsSession, $logger);
        $this->collection = 'adGroupCriterions';
        $this->adGroupCriterionService = $adGroupCriterionService;
        $this->keyField = 'criterion.id';
    }

    /**
     * @param array $ids
     * @param array $predicates
     * @return AdGroupCriterion[]
     * @throws \Google\AdsApi\AdWords\v201802\cm\ApiException
     */
    public function getAll(array $ids, array $predicates = []): array
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
                $fromService = (array)$this->doRequest(function () use ($selector) {
                    return $this->adGroupCriterionService->get($selector)->getEntries();
                });
                foreach ($fromService as $criterionItem) {
                    $index = $indexBy($criterionItem);
                    $criterions[$index] = $criterionItem;
                }
                $this->addToCache($fromService);
            }
        }
        return $criterions;
    }

    /**
     * @param int $id
     * @return AdGroupCriterion
     * @throws \Google\AdsApi\AdWords\v201802\cm\ApiException
     */
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
     * @param array $predicates
     * @return AdGroupCriterion[]
     * @throws \Google\AdsApi\AdWords\v201802\cm\ApiException
     */
    public function getByAdGroupIds(array $adGroupIds, array $predicates = []): array
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
            $fromService = (array)$this->doRequest(function () use ($selector) {
                return $this->adGroupCriterionService->get($selector)->getEntries();
            });
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
     * @param array $predicates
     * @return AdGroupCriterion[]
     * @throws \Google\AdsApi\AdWords\v201802\cm\ApiException
     */
    public function getByCampaignIds(array $campaignIds, array $predicates = []): array
    {
        $notFound = $campaignIds;

        $indexBy = function (AdGroupCriterion $criterion) {
            return $criterion->getBaseCampaignId() . $criterion->getAdGroupId() . $criterion->getCriterion()->getId();
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
            $perPageSize = 10000;
            $selector->setPaging(new Paging(0, $perPageSize));
            $page = 0;
            do {
                $page++;
                $pageResult = $this->doRequest(function () use ($selector) {
                    return $this->adGroupCriterionService->get($selector);
                });

                $fromService = (array)$pageResult->getEntries();
                foreach ($fromService as $criterionItem) {
                    $index = $indexBy($criterionItem);
                    $criterions[$index] = $criterionItem;
                }
                $this->logger->info('Getting criterions. Page ' . $page . '. Total: ' . $pageResult->getTotalNumEntries() . ' Already obtained: ' . \count($criterions));
                $selector->setPaging(new Paging($selector->getPaging()->getStartIndex() + $perPageSize, $perPageSize));
            } while ($pageResult->getTotalNumEntries() >= $page * $perPageSize);
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
        $updateOperations = [];
        $this->logger->info('Build operations');
        foreach ($entities as $entity) {
            $updateOperation = new AdGroupCriterionOperation();
            $updateOperation->setOperand($entity);
            $updateOperation->setOperator(Operator::SET);
            $updateOperations[] = $updateOperation;
        }
        $this->logger->info('Update operations: ' . \count($updateOperations));


        foreach (array_chunk($updateOperations, self::MAX_OPERATIONS_SIZE) as $i => $updateChunk) {
            /**
             * @var AdGroupCriterionOperation[] $updateChunk
             */
            $this->logger->info('Update chunk #' . $i . '. Size: ' . \count($updateChunk));
            $jobResults = $this->runMutateJob($updateChunk);
            $this->processJobResult($result, $jobResults);
        }

        $this->logger->info('Done');
        $this->clearCache();
        return $result;
    }

    /**
     * @param Operand $operand
     * @return AdGroupCriterion|mixed
     */
    protected function getOperandEntity(Operand $operand)
    {
        return $operand->getAdGroupCriterion();
    }
}