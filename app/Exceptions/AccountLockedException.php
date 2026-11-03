<?php declare(strict_types=1);
namespace App\Exceptions;
use DateTimeInterface;
class AccountLockedException extends \RuntimeException
{
    public function __construct(string $message, public readonly ?DateTimeInterface $lockedUntil = null) { parent::__construct($message); }
    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json(['status'=>'error','message'=>$this->getMessage(),'code'=>'ACCOUNT_LOCKED','data'=>['locked_until'=>$this->lockedUntil?->format(DateTimeInterface::ATOM)]],423);
    }
}
