<?php declare(strict_types=1);
namespace App\Exceptions;
class ImmutableRecordException extends \RuntimeException
{
    public function __construct(string $model) { parent::__construct("{$model} records are immutable."); }
    public function render(): \Illuminate\Http\JsonResponse { return response()->json(['status'=>'error','message'=>$this->getMessage(),'code'=>'IMMUTABLE_RECORD'],422); }
}
