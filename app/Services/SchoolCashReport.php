<?php
namespace App\Services;
use App\Models\{FeePayment, FinancialAdjustment, SchoolExpense};
use App\Support\Money;
use Carbon\Carbon;
class SchoolCashReport {
 public function month(string $date): array {
  $start=Carbon::parse($date)->startOfMonth(); $end=$start->copy()->endOfMonth(); $days=[]; $totals=[];
  foreach (range(1, $start->daysInMonth) as $day) $days[$start->copy()->day($day)->toDateString()]=[];
  $add=function($date,$currency,$kind,$amount) use (&$days,&$totals) {
   $empty=['receipts'=>0,'refunds'=>0,'expenses'=>0];
   $days[$date][$currency] ??= $empty; $totals[$currency] ??= $empty;
   $days[$date][$currency][$kind]+=$amount; $totals[$currency][$kind]+=$amount;
  };
  foreach(FeePayment::whereBetween('paid_on',[$start->toDateString(),$end->toDateString()])->get() as $p) $add($p->paid_on->toDateString(),$p->currency,'receipts',Money::cents($p->amount));
  foreach(FinancialAdjustment::join('fees','fees.id','=','financial_adjustments.fee_id')->where('kind','refund')->whereBetween('financial_adjustments.created_at',[$start,$end])->select('financial_adjustments.*','fees.currency')->get() as $p) $add($p->created_at->toDateString(),$p->currency,'refunds',Money::cents($p->amount));
  foreach(SchoolExpense::whereBetween('spent_on',[$start->toDateString(),$end->toDateString()])->get() as $p) $add($p->spent_on->toDateString(),$p->currency,'expenses',Money::cents($p->amount));
  foreach($totals as &$total) $total['remaining']=$total['receipts']-$total['refunds']-$total['expenses'];
  return ['days'=>$days,'totals'=>$totals,'month'=>$start];
 }
}
