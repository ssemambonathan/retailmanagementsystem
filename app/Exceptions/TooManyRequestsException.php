<?php declare(strict_types=1);
namespace App\Exceptions;
class TooManyRequestsException extends \RuntimeException {
    public function render(): \Illuminate\Http\JsonResponse { return response()->json(['status'=>'error','message'=>$this->getMessage(),'code'=>'TOO_MANY_REQUESTS'],429); }
}
