<?php
namespace Gt\Promise;

enum PromiseState {
	case PENDING;
	case RESOLVED;
	case REJECTED;
}
