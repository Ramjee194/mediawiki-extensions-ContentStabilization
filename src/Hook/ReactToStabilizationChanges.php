<?php

namespace MediaWiki\Extension\ContentStabilization\Hook;

use DeferredUpdates;
use MediaWiki\Extension\ContentStabilization\Event\StablePointAdded;
use MediaWiki\Extension\ContentStabilization\Hook\Interfaces\ContentStabilizationStablePointAddedHook;
use MediaWiki\Extension\ContentStabilization\Hook\Interfaces\ContentStabilizationStablePointMovedHook;
use MediaWiki\Extension\ContentStabilization\Hook\Interfaces\ContentStabilizationStablePointRemovedHook;
use MediaWiki\Extension\ContentStabilization\Hook\Interfaces\ContentStabilizationStablePointUpdatedHook;
use MediaWiki\Extension\ContentStabilization\StabilizationLog;
use MediaWiki\Extension\ContentStabilization\StablePoint;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\Authority;
use MWStake\MediaWiki\Component\Events\Notifier;

class ReactToStabilizationChanges implements
	ContentStabilizationStablePointRemovedHook,
	ContentStabilizationStablePointAddedHook,
	ContentStabilizationStablePointUpdatedHook,
	ContentStabilizationStablePointMovedHook
{

	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/** @var Notifier */
	private $notifier;

	/** @var StabilizationLog */
	private $specialLogLogger;

	/**
	 * @param WikiPageFactory $wikiPageFactory
	 * @param Notifier $notifier
	 * @param StabilizationLog $spLogger
	 */
	public function __construct(
		WikiPageFactory $wikiPageFactory, Notifier $notifier, StabilizationLog $spLogger
	) {
		$this->wikiPageFactory = $wikiPageFactory;
		$this->notifier = $notifier;
		$this->specialLogLogger = $spLogger;
	}

	/**
	 * @inheritDoc
	 */
	public function onContentStabilizationStablePointMoved( StablePoint $oldPoint, StablePoint $newPoint ): void {
		$this->runUpdates( $oldPoint );
		$this->runUpdates( $newPoint );

		$this->notifier->emit( new StablePointAdded( $newPoint ) );
	}

	/**
	 * @inheritDoc
	 */
	public function onContentStabilizationStablePointRemoved( StablePoint $removedPoint, Authority $remover ): void {
		$this->runUpdates( $removedPoint );
		$this->specialLogLogger->stablePointRemoved( $removedPoint, $remover );
	}

	/**
	 * @inheritDoc
	 */
	public function onContentStabilizationStablePointAdded( StablePoint $stablePoint ): void {
		$this->runUpdates( $stablePoint );

		$this->notifier->emit( new StablePointAdded( $stablePoint ) );
		$this->specialLogLogger->stablePointAdded( $stablePoint );
	}

	/**
	 * @inheritDoc
	 */
	public function onContentStabilizationStablePointUpdated( StablePoint $updatedPoint ): void {
		$this->runUpdates( $updatedPoint );

		$this->notifier->emit( new StablePointAdded( $updatedPoint ) );
		$this->specialLogLogger->stablePointUpdated( $updatedPoint );
	}

	/**
	 * @param StablePoint $point
	 *
	 * @return void
	 */
	private function runUpdates( StablePoint $point ) {
		$wikiPage = $this->wikiPageFactory->newFromTitle( $point->getPage() );
		$wikiPage->doSecondaryDataUpdates( [
			'triggeringUser' => $point->getApprover()->getUser(),
			'defer' => DeferredUpdates::POSTSEND
		] );
	}
}
