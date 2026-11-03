<?php declare(strict_types=1);
namespace App\Exceptions;
class TokenRevokedException extends \RuntimeException {
    public function render(): \Illuminate\Http\JsonResponse { return response()->json(['status'=>'error','message'=>$this->getMessage(),'code'=>'TOKEN_REVOKED'],401); }
}
