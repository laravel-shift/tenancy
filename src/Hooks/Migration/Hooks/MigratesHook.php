<?php

declare(strict_types=1);

/*
 * This file is part of the tenancy/tenancy package.
 *
 * Copyright Tenancy for Laravel
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @see https://tenancy.dev
 * @see https://github.com/tenancy
 */

namespace Tenancy\Hooks\Migration\Hooks;

use Illuminate\Database\Migrations\Migrator;
use Tenancy\Affects\Connections\Contracts\ResolvesConnections;
use Tenancy\Facades\Tenancy;
use Tenancy\Hooks\Migration\Events\ConfigureMigrations;
use Tenancy\Lifecycle\ConfigurableHook;
use Tenancy\Tenant\Events\Deleted;

class MigratesHook extends ConfigurableHook
{
    public Migrator $migrator;

    public string $connection;

    public ResolvesConnections $resolver;

    public $action;

    public int $priority = -50;

    public array $paths;

    public function __construct()
    {
        $this->migrator = resolve('migrator');
        $this->connection = Tenancy::getTenantConnectionName();
        $this->resolver = resolve(ResolvesConnections::class);

        $this->paths = $this->migrator->paths();
    }

    public function for($event): static
    {
        $this->action = $event instanceof Deleted ? 'reset' : 'run';

        parent::for($event);

        event(new ConfigureMigrations($event, $this));

        return $this;
    }

    public function fire(): void
    {
        $db = resolve('db');
        $default = $db->getDefaultConnection();

        $this->resolver->__invoke($this->event->tenant, $this->connection);
        $this->migrator->setConnection($this->connection);

        if (!$this->migrator->repositoryExists()) {
            $this->migrator->getRepository()->createRepository();
        }
        call_user_func([$this->migrator, $this->action], $this->paths);

        $this->resolver->__invoke(null, $this->connection);
        $db->setDefaultConnection($default);
    }
}
