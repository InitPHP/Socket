<?php
/**
 * BaseServer.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.io/license.txt  MIT
 * @version    1.0
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Socket\Common;

use InitPHP\Socket\Interfaces\SocketServerClientInterface;
use InitPHP\Socket\Interfaces\SocketServerInterface;

use InitPHP\Socket\Server\ServerClient;
use function sleep;
use function usleep;
use function call_user_func_array;
use function socket_accept;
use function array_search;
use function is_iterable;
use function is_int;

abstract class BaseServer implements SocketServerInterface
{
    use BaseCommon;

    /** @var SocketServerClientInterface[] */
    protected array $clients = [];

    /** @var array<string|int, int> */
    protected array $clientMap = [];

    abstract public function connection(): SocketServerInterface;

    abstract public function disconnect(): bool;

    public function getClients(): array
    {
        return $this->clients;
    }

    public function clientRegister($id, SocketServerClientInterface $client): bool
    {
        try {
            $index = array_search($client, $this->clients);
            if ($index === false) {
                return false;
            }
            $this->clientMap[$id] = $index;
            $this->clients[$index]->setId($id);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param string $message
     * @param array|string|int|null $clients
     * @return bool
     */
    public function broadcast(string $message, $clients = null): bool
    {
        try {
            if ($clients !== null) {
                !is_iterable($clients) && $clients = [$clients];
                foreach ($clients as $id) {
                    isset($this->clients[$this->clientMap[$id]]) && $this->clients[$this->clientMap[$id]]->push($message);
                }
            } else {
                foreach ($this->clients as $address => $client) {
                    $client->push($message);
                }
            }

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function live(callable $callback, int $usleep = 100000): void
    {
        while (true) {
            if ($clientSocket = socket_accept($this->socket)) {
                $client = (new ServerClient())->__setSocket($clientSocket);
                $this->clients[] = $client;
            }
            foreach ($this->clients as $index => $client) {
                if ($client->isDisconnected()) {
                    unset($this->clients[$index]);
                    continue;
                }
                call_user_func_array($callback, [$this, $client]);
            }

            $usleep < 1000 && $usleep = 1000;
            $this->wait($usleep / 1000000);
        }
    }

    public function wait($second): void
    {
        if ($second < 0) {
            throw new \InvalidArgumentException("Waiting time cannot be less than 0.");
        }
        if (is_int($second)) {
            sleep($second);
        } else {
            usleep($second * 1000000);
        }
    }

}
