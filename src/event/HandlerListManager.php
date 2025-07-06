<?php

declare(strict_types=1);

namespace pocketmine\event;

use pocketmine\plugin\Plugin;
use pocketmine\utils\Utils;
use ReflectionClass;

class HandlerListManager{

	private static ?self $globalInstance = null;

	public static function global() : self{
		return self::$globalInstance ??= new self();
	}

	/** @var HandlerList[] classname => HandlerList */
	private array $allLists = [];

	/** @var RegisteredListenerCache[] event class name => cache */
	private array $handlerCaches = [];

	public function unregisterAll(RegisteredListener|Plugin|Listener|null $object = null) : void{
		if($object instanceof Listener || $object instanceof Plugin || $object instanceof RegisteredListener){
			foreach($this->allLists as $list){
				$list->unregister($object);
			}
		}else{
			foreach($this->allLists as $list){
				$list->clear();
			}
		}
	}

	private static function isValidClass(ReflectionClass $class) : bool{
		$tags = Utils::parseDocComment((string) $class->getDocComment());
		return !$class->isAbstract() || isset($tags["allowHandle"]);
	}

	private static function resolveNearestHandleableParent(ReflectionClass $class) : ?ReflectionClass{
		for($parent = $class->getParentClass(); $parent !== false; $parent = $parent->getParentClass()){
			if(self::isValidClass($parent)){
				return $parent;
			}
		}
		return null;
	}

	public function getListFor(string $event) : HandlerList{
		if(isset($this->allLists[$event])){
			return $this->allLists[$event];
		}

		$class = new ReflectionClass($event);
		if(!self::isValidClass($class)){
			throw new \InvalidArgumentException("Event must be non-abstract or have the @allowHandle annotation");
		}

		$parent = self::resolveNearestHandleableParent($class);
		$cache = new RegisteredListenerCache();
		$this->handlerCaches[$event] = $cache;

		return $this->allLists[$event] = new HandlerList(
			$event,
			parentList: $parent !== null ? $this->getListFor($parent->getName()) : null,
			handlerCache: $cache
		);
	}

	/**
	 * Returns all the listeners for a specific event class.
	 *
	 * @param class-string<covariant Event> $event
	 * @return RegisteredListener[]
	 */
	public function getHandlersFor(string $event) : array{
		$cache = $this->handlerCaches[$event] ?? null;
		return $cache?->list ?? $this->getListFor($event)->getListenerList();
	}

	/**
	 * Checks whether any handlers are registered for the given event class.
	 *
	 * @param class-string<covariant Event> $event
	 */
	public function hasHandlersFor(string $event) : bool{
		$cache = $this->handlerCaches[$event] ?? null;
		if($cache !== null && $cache->list !== null){
			return $cache->list !== [];
		}

		$list = $this->getListFor($event);
		return $list->getListenerList() !== [];
	}

	/**
	 * @return HandlerList[]
	 */
	public function getAll() : array{
		return $this->allLists;
	}
}
