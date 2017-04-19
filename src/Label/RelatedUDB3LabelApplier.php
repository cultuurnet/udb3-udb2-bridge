<?php

namespace CultuurNet\UDB3\UDB2\Label;

use Broadway\Domain\AggregateRoot;
use CultuurNet\UDB3\Event\Event;
use CultuurNet\UDB3\Label;
use CultuurNet\UDB3\Label\ReadModels\JSON\Repository\ReadRepositoryInterface as LabelsRepositoryInterface;
use CultuurNet\UDB3\Label\ReadModels\Relations\Repository\ReadRepositoryInterface as LabelsRelationsRepositoryInterface;
use CultuurNet\UDB3\Label\ValueObjects\Visibility;
use CultuurNet\UDB3\Organizer\Organizer;
use CultuurNet\UDB3\Place\Place;
use Psr\Log\LoggerInterface;
use ValueObjects\StringLiteral\StringLiteral;

class RelatedUDB3LabelApplier implements LabelApplierInterface
{
    /**
     * @var LabelsRelationsRepositoryInterface
     */
    private $labelsRelationsRepository;

    /**
     * @var LabelsRepositoryInterface
     */
    private $labelsRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param LabelsRelationsRepositoryInterface $labelsRelationsRepository
     * @param LabelsRepositoryInterface $labelsRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        LabelsRelationsRepositoryInterface $labelsRelationsRepository,
        LabelsRepositoryInterface $labelsRepository,
        LoggerInterface $logger
    ) {
        $this->labelsRelationsRepository = $labelsRelationsRepository;
        $this->labelsRepository = $labelsRepository;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     * @param Event|Place|Organizer $aggregateRoot
     */
    public function apply(AggregateRoot $aggregateRoot)
    {
        $labelRelations = $this->labelsRelationsRepository->getLabelRelationsForItem(
            new StringLiteral($aggregateRoot->getAggregateRootId())
        );

        /** @var Label[] $udb3Labels */
        $udb3Labels = [];

        foreach ($labelRelations as $labelRelation) {
            if (!$labelRelation->isImported()) {
                $labelName = $labelRelation->getLabelName();
                $label = $this->labelsRepository->getByName($labelName);

                if ($label) {
                    $this->logger->info(
                        'Found udb3 label ' . $label->getName()->toNative()
                        . ' for aggregate ' . $aggregateRoot->getAggregateRootId()
                    );

                    $udb3Labels[] = new Label(
                        $labelRelation->getLabelName()->toNative(),
                        $label->getVisibility() === Visibility::VISIBLE()
                    );
                }
            }
        }

        foreach ($udb3Labels as $udb3Label) {
            $aggregateRoot->addLabel($udb3Label);
            $this->logger->info(
                'Added udb3 label ' . $udb3Label
                . ' for aggregate ' . $aggregateRoot->getAggregateRootId()
            );
        }
    }
}
