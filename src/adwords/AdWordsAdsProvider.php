<?php

namespace sitkoru\contextcache\adwords;


use Google\AdsApi\AdWords\AdWordsSession;
use Google\AdsApi\AdWords\v201708\cm\AdGroupAd;
use Google\AdsApi\AdWords\v201708\cm\AdGroupAdOperation;
use Google\AdsApi\AdWords\v201708\cm\AdGroupAdService;
use Google\AdsApi\AdWords\v201708\cm\AdGroupAdStatus;
use Google\AdsApi\AdWords\v201708\cm\Operand;
use Google\AdsApi\AdWords\v201708\cm\Operator;
use Google\AdsApi\AdWords\v201708\cm\Predicate;
use Google\AdsApi\AdWords\v201708\cm\PredicateOperator;
use Google\AdsApi\AdWords\v201708\cm\Selector;
use Google\AdsApi\AdWords\v201708\cm\TempAdUnionId;
use Google\AdsApi\AdWords\v201708\cm\TemplateAd;
use Psr\Log\LoggerInterface;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\IEntitiesProvider;
use sitkoru\contextcache\common\models\UpdateResult;
use sitkoru\contextcache\helpers\ArrayHelper;

class AdWordsAdsProvider extends AdWordsEntitiesProvider implements IEntitiesProvider
{
    /**
     * @var AdGroupAdService
     */
    private $adGroupAdService;

    private static $fields = [
        'AdGroupId',
        'AdType',
        'AdvertisingId',
        'BaseAdGroupId',
        'BaseCampaignId',
        'BusinessName',
        'CallOnlyAdBusinessName',
        'CallOnlyAdCallTracked',
        'CallOnlyAdConversionTypeId',
        'CallOnlyAdCountryCode',
        'CallOnlyAdDescription1',
        'CallOnlyAdDescription2',
        'CallOnlyAdDisableCallConversion',
        'CallOnlyAdPhoneNumber',
        'CallOnlyAdPhoneNumberVerificationUrl',
        'CreationTime',
        'CreativeFinalAppUrls',
        'CreativeFinalMobileUrls',
        'CreativeFinalUrls',
        'CreativeTrackingUrlTemplate',
        'CreativeUrlCustomParameters',
        'Description',
        'Description1',
        'Description2',
        'DevicePreference',
        'Dimensions',
        'DisplayUrl',
        'ExpandingDirections',
        'FileSize',
        'Headline',
        'HeadlinePart1',
        'HeadlinePart2',
        'Height',
        'Id',
        'ImageCreativeName',
        'IndustryStandardCommercialIdentifier',
        'IsCookieTargeted',
        'IsTagged',
        'IsUserInterestTargeted',
        'Labels',
        'LogoImage',
        'LongHeadline',
        'MarketingImage',
        'MediaId',
        'MimeType',
        'Path1',
        'Path2',
        'PolicySummary',
        'ReadyToPlayOnTheWeb',
        'ReferenceId',
        'RichMediaAdCertifiedVendorFormatId',
        'RichMediaAdDuration',
        'RichMediaAdImpressionBeaconUrl',
        'RichMediaAdName',
        'RichMediaAdSnippet',
        'RichMediaAdSourceUrl',
        'RichMediaAdType',
        'ShortHeadline',
        'SourceUrl',
        'Status',
        'TemplateAdDuration',
        'TemplateAdName',
        'TemplateAdUnionId',
        'TemplateElementFieldName',
        'TemplateElementFieldText',
        'TemplateElementFieldType',
        'TemplateId',
        'TemplateOriginAdId',
        'UniqueName',
        'Url',
        'UrlData',
        'Urls',
        'VideoTypes',
        'Width',
        'YouTubeVideoIdString'
    ];

    public function __construct(
        AdGroupAdService $adGroupService,
        ICacheProvider $cacheProvider,
        AdWordsSession $adWordsSession,
        LoggerInterface $logger
    ) {
        parent::__construct($cacheProvider, $adWordsSession, $logger);
        $this->collection = 'adGroupAds';
        $this->adGroupAdService = $adGroupService;
    }

    /**
     * @param array $ids
     * @return AdGroupAd[]
     * @throws \Google\AdsApi\AdWords\v201708\cm\ApiException
     */
    public function getAll(array $ids): array
    {
        $notFound = $ids;
        /**
         * @var AdGroupAd[] $adGroupAds
         */
        $adGroupAds = $this->getFromCache($ids, 'ad.id');
        if ($adGroupAds) {
            $found = array_keys($adGroupAds);
            $notFound = array_values(array_diff($ids, $found));
        }
        if ($notFound) {
            $selector = new Selector();
            $selector->setFields(self::$fields);
            $predicates[] = new Predicate('Id', PredicateOperator::IN, $ids);
            $selector->setPredicates($predicates);
            $fromService = (array)$this->adGroupAdService->get($selector)->getEntries();
            foreach ($fromService as $adGroupAdItem) {
                $adGroupAds[$adGroupAdItem->getAd()->getId()] = $adGroupAdItem;
            }
            $this->addToCache($fromService);
        }
        return $adGroupAds;
    }

    public function getOne($id): AdGroupAd
    {
        $ads = $this->getAll([$id]);
        if ($ads) {
            return reset($ads);
        }
        return null;
    }

    /**
     * @param int[] $campaignIds
     * @return AdGroupAd[]
     * @throws \Exception
     */
    public function getByCampaignIds(array $campaignIds): array
    {
        $notFound = $campaignIds;

        /**
         * @var AdGroupAd[] $adGroupAds
         */
        $adGroupAds = $this->getFromCache($campaignIds, 'campaignId', 'ad.id');
        if ($adGroupAds) {
            $found = array_unique(ArrayHelper::getColumn($adGroupAds, 'campaignId'));
            $notFound = array_values(array_diff($campaignIds, $found));
        }
        if ($notFound) {
            $selector = new Selector();
            $selector->setFields(self::$fields);
            $predicates[] = new Predicate('CampaignId', PredicateOperator::IN, $notFound);
            $selector->setPredicates($predicates);
            $fromService = (array)$this->adGroupAdService->get($selector)->getEntries();
            foreach ($fromService as $adGroupAdItem) {
                /**
                 * @var AdGroupAd $adGroupAdItem
                 */
                $adGroupAds[$adGroupAdItem->getAd()->getId()] = $adGroupAdItem;
            }
            $this->addToCache($fromService);
        }
        return $adGroupAds;
    }

    /**
     * @param array $adGroupIds
     * @return AdGroupAd[]
     * @throws \Google\AdsApi\AdWords\v201708\cm\ApiException
     */
    public function getByAdGroupIds(array $adGroupIds): array
    {
        $notFound = $adGroupIds;

        /**
         * @var AdGroupAd[] $adGroupAds
         */
        $adGroupAds = $this->getFromCache($adGroupIds, 'adGroupId', 'ad.id');
        if ($adGroupAds) {
            $found = array_unique(ArrayHelper::getColumn($adGroupAds, 'adGroupId'));
            $notFound = array_values(array_diff($adGroupIds, $found));
        }
        if ($notFound) {
            $selector = new Selector();
            $selector->setFields(self::$fields);
            $predicates[] = new Predicate('AdGroupId', PredicateOperator::IN, $notFound);
            $selector->setPredicates($predicates);
            $fromService = (array)$this->adGroupAdService->get($selector)->getEntries();
            foreach ($fromService as $adGroupAdItem) {
                /**
                 * @var AdGroupAd $adGroupAdItem
                 */
                $adGroupAds[$adGroupAdItem->getAd()->getId()] = $adGroupAdItem;
            }
            $this->addToCache($fromService);
        }
        return $adGroupAds;
    }

    /**
     * @param AdGroupAd[] $entities
     * @return UpdateResult
     * @throws \Exception
     */
    public function update(array $entities): UpdateResult
    {
        $result = new UpdateResult();
        $deleteOperations = [];
        /**
         * @var AdGroupAdOperation[] $addOperations
         */
        $addOperations = [];
        $this->logger->info('Build operations');
        foreach ($entities as $entity) {
            $newAd = clone $entity->getAd();
            $entity->setStatus(AdGroupAdStatus::DISABLED);
            $deleteOperation = new AdGroupAdOperation();
            $deleteOperation->setOperand($entity);
            $deleteOperation->setOperator(Operator::REMOVE);
            $newAd->setId(null);
            if ($newAd instanceof TemplateAd) {
                $newAd->setAdUnionId(new TempAdUnionId());
                break;
            }
            $adGroupAd = new AdGroupAd();
            $adGroupAd->setAdGroupId($entity->getAdGroupId());
            $adGroupAd->setStatus(AdGroupAdStatus::ENABLED);
            $adGroupAd->setAd($newAd);
            $addOperation = new AdGroupAdOperation();
            $addOperation->setOperand($adGroupAd);
            $addOperation->setOperator(Operator::ADD);
            $deleteOperations[$entity->getAd()->getId()] = $deleteOperation;
            $addOperations[] = $addOperation;
        }

        $this->logger->info('Add operations: ' . count($addOperations));


        foreach (array_chunk($addOperations, self::MAX_OPERATIONS_SIZE) as $i => $addChunk) {
            /**
             * @var AdGroupAdOperation[] $addChunk
             */
            $this->logger->info('Add chunk #' . $i . '. Size: ' . count($addChunk));
            $jobResults = $this->runMutateJob($addChunk);
            $this->processJobResult($result, $jobResults);
            if (!$result->success) {
                foreach ($result->errors as $adOperationId => $errors) {
                    $adOperation = $addChunk[$adOperationId];
                    $adId = $adOperation->getOperand()->getAd()->getId();
                    unset($deleteOperations[$adId]);
                }
            }
        }

        $this->logger->info('Delete succeeded ads: ' . count($deleteOperations));
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
        return $operand->getAdGroupAd();
    }
}