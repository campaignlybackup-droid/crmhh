<?php

namespace App\Events;

class EventDispatcher {
    private static $listeners = [];

    /**
     * Register a listener for an event.
     *
     * @param string $eventName
     * @param callable $listener
     */
    public static function listen(string $eventName, callable $listener) {
        if (!isset(self::$listeners[$eventName])) {
            self::$listeners[$eventName] = [];
        }
        self::$listeners[$eventName][] = $listener;
    }

    /**
     * Dispatch an event.
     *
     * @param string $eventName
     * @param mixed $payload
     */
    public static function dispatch(string $eventName, $payload = null) {
        if (isset(self::$listeners[$eventName])) {
            foreach (self::$listeners[$eventName] as $listener) {
                call_user_func($listener, $payload);
            }
        }
    }
}
