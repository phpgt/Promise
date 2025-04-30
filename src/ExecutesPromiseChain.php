<?php
namespace Gt\Promise;

use Gt\Promise\Chain\CatchChain;
use Gt\Promise\Chain\Chainable;
use Gt\Promise\Chain\ChainFunctionTypeError;
use Gt\Promise\Chain\FinallyChain;
use Gt\Promise\Chain\ThenChain;

trait ExecutesPromiseChain {
	private function complete():void {
		usort($this->chain, $this->sortChainItems(...));

		while($this->getState() !== PromiseState::PENDING) {
			$chainItem = $this->getNextChainItem();
			if(!$chainItem) {
				break;
			}

			if($this->shouldSkipResolution($chainItem)) {
				continue;
			}

			if($chainItem instanceof ThenChain) {
				$this->executeThen($chainItem);
			}
			elseif($chainItem instanceof FinallyChain) {
				$this->executeFinally($chainItem);
			}
			elseif($chainItem instanceof CatchChain) {
				$this->executeCatch($chainItem);
			}
		}

		$this->throwUnhandledRejection();
	}

	private function shouldSkipResolution(Chainable $chainItem):bool {
		if($chainItem instanceof ThenChain || $chainItem instanceof FinallyChain) {
			try {
				if($this->resolvedValueSet && isset($this->resolvedValue)) {
					$chainItem->checkResolutionCallbackType($this->resolvedValue);
				}
			}
			catch(ChainFunctionTypeError) {
				return true;
			}
		}
		elseif($chainItem instanceof CatchChain) {
			try {
				if(isset($this->rejectedReason)) {
					$chainItem->checkRejectionCallbackType($this->rejectedReason);
				}
			}
			catch(ChainFunctionTypeError) {
				return true;
			}
		}
		return false;
	}

	private function executeThen(ThenChain $chainItem):void {
		if($this->handleThen($chainItem)) {
			$this->emptyChain();
		}
	}

	private function executeFinally(FinallyChain $chainItem):void {
		if($this->handleFinally($chainItem)) {
			$this->emptyChain();
		}
	}

	private function executeCatch(CatchChain $chainItem):void {
		if($handled = $this->handleCatch($chainItem)) {
			array_push($this->handledRejections, $handled);
		}
	}

	private function sortChainItems(Chainable $a, Chainable $b):int {
		if($a instanceof FinallyChain && !($b instanceof FinallyChain)) {
			return 1;
		}
		if($b instanceof FinallyChain && !($a instanceof FinallyChain)) {
			return -1;
		}
		return 0;
	}
}
