Simple PHP lock
===============

A simple library for processing and clearing locks in your PHP application. No dependencies are required for use, everything is done in native PHP.

Idea
----

Simple and efficient lock management in your PHP application.

To use it, just start a lock (you can use many different locks at the same time), wait for it to unlock, process any competing tasks, and then let the lock expire or unlock manually.

If you let the lock expire naturally, it will be automatically cleared via the Garbage collector component.

📦 Installation
---------------

It's best to use [Composer](https://getcomposer.org) for installation, and you can also find the package on
[Packagist](https://packagist.org/packages/baraja-core/lock) and
[GitHub](https://github.com/baraja-core/lock).

To install, simply use the command:

```
$ composer require baraja-core/lock
```

How to use
----------

Simply request a static `Lock` service over which you can perform the following operations:

```php
// start no-name transaction
Lock::startTransaction();

if (Lock::isTransactionRunning()) {
    // Transaction is running...
}

// Stop no-name transaction
Lock::stopTransaction();
```

By default, you should always stop a transaction at the end of an operation using the `stopTransaction()` method. If you do not terminate the transaction, it will be terminated automatically when the protection limit expires.

The guard limit cannot be disabled and is used for cases where stopping a transaction fails for any reason, to avoid completely breaking the application. The application can always get itself out of a broken lock.

Named transactions and set your own limit
-----------------------------------------

In the case of competing processes, we first need to wait for the previous transaction to complete.

To do this, it is ideal to use the `wait()` method, which automatically waits for the previous transaction to complete.

After waiting for a free time slot, we create our own transaction in the current process, which we manually terminate later.

```php
Lock::wait('order-number');

// start transaction "order-number" for 5 seconds
Lock::startTransaction('order-number', 5000);

// run something special...

// stop transaction
Lock::stopTransaction('order-number');
```

If the transaction is not manually stopped by the `stopTransaction()` method, it will be automatically terminated after the protection interval expires.

If stopping the transaction fails directly at the system level for some reason (for example, you do not have the rights to delete the transaction file), the `wait()` method will drop the request after 30 seconds at the latest (the interval can be set), even if the lock still exists.

This ensures that the application never gets completely stuck.

📄 License
-----------

`baraja-core/lock` is licensed under the MIT license. See the [LICENSE](https://github.com/baraja-core/lock/blob/master/LICENSE) file for more details.
