<?php
namespace GT\Promise;

enum PromiseState {
	case PENDING;
	case RESOLVED;
	case REJECTED;
}
