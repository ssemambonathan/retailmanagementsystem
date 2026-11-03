<?php declare(strict_types=1);
namespace App\Exceptions;
class InvalidTokenException extends \RuntimeException {
    public function render(): \Illuminate\Http\JsonResponse { return response()->json(['status'=>'error','message'=>$this->getMessage(),'code'=>'INVALID_TOKEN'],422); }
}
