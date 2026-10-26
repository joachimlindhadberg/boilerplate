<?php

declare(strict_types=1);

/** Documented Core hook signatures. Unknown names remain allowed for plugin-owned events. */
final class ModuleHookContracts
{
    /** @return array<string,array{kind:string,parameters:array<int,string>}> */
    public static function coreEvents(): array
    {
        return [
            'modules.booted' => ['kind' => 'action', 'parameters' => []],
            'modules.activated' => ['kind' => 'action', 'parameters' => ['slug:string', 'version:string']],
            'modules.deactivated' => ['kind' => 'action', 'parameters' => ['slug:string', 'version:string']],
            'modules.updated' => ['kind' => 'action', 'parameters' => ['slug:string', 'fromVersion:string', 'toVersion:string']],
        ];
    }

    public static function assertKind(string $name, string $kind): void
    {
        $entry = self::coreEvents()[$name] ?? null;
        if ($entry !== null && $entry['kind'] !== $kind) {
            throw new InvalidArgumentException('Core hook kind mismatch: ' . $name);
        }
    }

    public static function assertDispatch(string $name, string $kind, array $args): void
    {
        self::assertKind($name, $kind);
        $contract = self::coreEvents()[$name] ?? null;
        if ($contract === null) return;
        if (count($args) !== count($contract['parameters'])) {
            throw new InvalidArgumentException('Invalid number of arguments for Core hook: ' . $name);
        }
        foreach ($args as $index => $value) {
            if (!is_string($value)) {
                throw new InvalidArgumentException('Invalid argument type for Core hook: ' . $name);
            }
        }
    }
}
