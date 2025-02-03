<?php

function creditScheculeRepayment($request, $uids, $creditSchedule){

$bayarPokok = $request->BAYAR_POKOK;
$bayarDiscountPokok = $request->DISKON_POKOK;
$bayarBunga = $request->BAYAR_BUNGA;
$bayarDiscountBunga = $request->DISKON_BUNGA;

$remaining_discount = $bayarDiscountPokok;
$remaining_discount_bunga = $bayarDiscountBunga;

foreach ($creditSchedule as $index => $res) {

$uid = $uids[$index];

$valBeforePrincipal = $res['PAYMENT_VALUE_PRINCIPAL'];
$valBeforeInterest = $res['PAYMENT_VALUE_INTEREST'];
$getPrincipal = $res['PRINCIPAL'];
$getInterest = $res['INTEREST'];
$getDiscountPrincipal = $res['DISCOUNT_PRINCIPAL'];
$getDiscountInterest = $res['DISCOUNT_INTEREST'];
$getInstallment = $res['INSTALLMENT'];

$new_payment_value_principal = $valBeforePrincipal;

if ($valBeforePrincipal < $getPrincipal) { $remaining_to_principal=$getPrincipal - $valBeforePrincipal; if ($bayarPokok>= $remaining_to_principal) {
    $new_payment_value_principal = $getPrincipal;
    $bayarPokok -= $remaining_to_principal;
    } else {
    $new_payment_value_principal += $bayarPokok;
    $bayarPokok = 0;
    }

    $updates = [];
    if ($new_payment_value_principal != $valBeforePrincipal) {
    $updates['PAYMENT_VALUE_PRINCIPAL'] = $new_payment_value_principal;
    $discountPaymentData = $this->preparePaymentData($uid, 'BAYAR_PELUNASAN_POKOK', $new_payment_value_principal);
    M_PaymentDetail::create($discountPaymentData);
    }

    // Apply discounts to remaining principal
    if ($remaining_discount > 0) {
    $remaining_to_principal_for_discount = $getPrincipal - $new_payment_value_principal;

    if ($remaining_discount >= $remaining_to_principal_for_discount) {
    $updates['DISCOUNT_PRINCIPAL'] = $remaining_to_principal_for_discount;
    $discountPaymentData = $this->preparePaymentData($uid, 'DISKON_POKOK', $remaining_to_principal_for_discount);
    M_PaymentDetail::create($discountPaymentData);

    $new_payment_value_principal += $remaining_to_principal_for_discount;
    $remaining_discount -= $remaining_to_principal_for_discount;
    } else {
    $updates['DISCOUNT_PRINCIPAL'] = $remaining_discount;
    $discountPaymentData = $this->preparePaymentData($uid, 'DISKON_POKOK', $remaining_discount);
    M_PaymentDetail::create($discountPaymentData);

    $new_payment_value_principal += $remaining_discount;
    $remaining_discount = 0;
    }
    }

    if ($valBeforeInterest < $getInterest) { $remaining_to_interest=$getInterest - $valBeforeInterest; $interestUpdates=$this->hitungBunga($bayarBunga, $remaining_to_interest, $remaining_discount_bunga, $res,$uid);
        $bayarBunga = $interestUpdates['bayarBunga'];
        $remaining_discount_bunga = $interestUpdates['remaining_discount_bunga'];
        }

        if (!empty($updates)) {
        $res->update($updates);
        }
        }

        $sumAll = floatval($valBeforePrincipal) + floatval($valBeforeInterest) + floatval($getPrincipal) + floatval($getInterest) + floatval($getDiscountPrincipal) + floatval($getDiscountInterest);
        $checkPaid = floatval($getInstallment) == floatval($sumAll);
        $insufficient = floatval($getInstallment) == floatval($checkPaid);

        $res->update([
        'INSUFFICIENT_PAYMENT' => $insufficient ? 0 : $insufficient,
        'PAYMENT_VALUE' => $sumAll,
        'PAID_FLAG' => $checkPaid ? 'PAID' : ''
        ]);

        // if ($remaining_discount <= 0) { // break; // } } } function arrearsRepayment($request,$loan_number, $uids){ $arrears=M_Arrears::where(['LOAN_NUMBER'=> $loan_number, 'STATUS_REC' => 'A'])->get();

            $bayarDenda = $request->BAYAR_DENDA;
            $bayarDiscountDenda = $request->DISKON_DENDA;
            $bayarPokok = $request->BAYAR_POKOK;
            $bayarDiscountPokok = $request->DISKON_POKOK;
            $bayarBunga = $request->BAYAR_BUNGA;
            $bayarDiscountBunga = $request->DISKON_BUNGA;

            $remaining_discount = $bayarDiscountPokok;
            $remaining_discount_bunga = $bayarDiscountBunga;
            $remaining_discount_denda = $bayarDiscountDenda;

            foreach ($arrears as $index => $res) {

            $uid = $uids[$index];

            $valBeforePrincipal = $res['PAID_PCPL'];
            $valBeforeInterest = $res['PAID_INT'];
            $getPrincipal = $res['PAST_DUE_PCPL'];
            $getInterest = $res['PAST_DUE_INTRST'];
            $valBeforePenalty = $res['PAID_PENALTY'];
            $getPenalty = $res['PAST_DUE_PENALTY'];

            $new_payment_value_principal = $valBeforePrincipal;
            $new_payment_value_penalty = $valBeforePenalty;

            if ($valBeforePrincipal < $getPrincipal) { $remaining_to_principal=$getPrincipal - $valBeforePrincipal; // If bayarPokok is enough to cover the remaining principal if ($bayarPokok>= $remaining_to_principal) {
                $new_payment_value_principal = $getPrincipal;
                $bayarPokok -= $remaining_to_principal;
                } else {
                $new_payment_value_principal += $bayarPokok;
                $bayarPokok = 0; // No more bayarPokok to apply
                }

                $updates = [];
                if ($new_payment_value_principal !== $valBeforePrincipal) {
                $updates['PAID_PCPL'] = $new_payment_value_principal;
                }

                if ($remaining_discount > 0) {
                $remaining_to_principal_for_discount = $getPrincipal - $new_payment_value_principal;

                // If the remaining discount can cover the remaining principal
                if ($remaining_discount >= $remaining_to_principal_for_discount) {
                $updates['WOFF_PCPL'] = $remaining_to_principal_for_discount;
                $new_payment_value_principal += $remaining_to_principal_for_discount; // Apply discount
                $remaining_discount -= $remaining_to_principal_for_discount; // Reduce the discount
                } else {
                // If the discount can't fully cover the remaining principal
                $updates['WOFF_PCPL'] = $remaining_discount;
                $new_payment_value_principal += $remaining_discount; // Apply discount
                $remaining_discount = 0; // No more discount left
                }
                }

                if ($valBeforeInterest < $getInterest) { $remaining_to_interest=$getInterest - $valBeforeInterest; $interestUpdates=$this->hitungBungaDenda($bayarBunga, $remaining_to_interest, $remaining_discount_bunga, $res, $uid);
                    $bayarBunga = $interestUpdates['bayarBunga']; // Update the remaining bayarBunga
                    $remaining_discount_bunga = $interestUpdates['remaining_discount_bunga']; // Update the remaining discount for bunga
                    }

                    if ($valBeforePenalty < $getPenalty) { $remaining_to_penalty=$getPenalty - $valBeforePenalty; // If bayarDenda is enough to cover the remaining penalty if ($bayarDenda>= $remaining_to_penalty) {
                        $new_payment_value_penalty = $getPenalty;
                        $bayarDenda -= $remaining_to_penalty;
                        } else {
                        $new_payment_value_penalty += $bayarDenda;
                        $bayarDenda = 0; // No more bayarDenda to apply
                        }

                        if ($new_payment_value_penalty !== $valBeforePenalty) {
                        $updates['PAID_PENALTY'] = $new_payment_value_penalty;
                        $discountPaymentData = $this->preparePaymentData($uid, 'BAYAR_PELUNASAN_DENDA', $new_payment_value_penalty);
                        M_PaymentDetail::create($discountPaymentData);
                        }

                        // Apply discount to denda
                        if ($remaining_discount_denda > 0) {
                        $remaining_to_penalty_for_discount = $getPenalty - $new_payment_value_penalty;

                        // If the remaining discount can cover the remaining penalty
                        if ($remaining_discount_denda >= $remaining_to_penalty_for_discount) {
                        $updates['WOFF_PENALTY'] = $remaining_to_penalty_for_discount;
                        $discountPaymentData = $this->preparePaymentData($uid, 'DISKON_DENDA', $remaining_to_penalty_for_discount);
                        M_PaymentDetail::create($discountPaymentData);
                        $new_payment_value_penalty += $remaining_to_penalty_for_discount; // Apply discount
                        $remaining_discount_denda -= $remaining_to_penalty_for_discount; // Reduce the discount
                        } else {
                        // If the discount can't fully cover the remaining penalty
                        $updates['WOFF_PENALTY'] = $remaining_discount_denda;
                        $discountPaymentData = $this->preparePaymentData($uid, 'DISKON_DENDA', $remaining_discount_denda);
                        M_PaymentDetail::create($discountPaymentData);
                        $new_payment_value_penalty += $remaining_discount_denda; // Apply discount
                        $remaining_discount_denda = 0; // No more discount left
                        }
                        }
                        }

                        if (!empty($updates)) {
                        $res->update($updates);
                        }
                        }

                        $res->update(['END_DATE' =>now(),'STATUS_REC' => $request->DISKON_DENDA == 0 ? 'S' :'D']);
                        if ($remaining_discount <= 0 && $bayarDiscountPokok !=0) { break; } } } function hitungBunga($bayarBunga, $remaining_to_interest, $remaining_discount_bunga, $res,$uid) { $new_payment_value_interest=$res['PAYMENT_VALUE_INTEREST']; $interestUpdates=[]; // If bayarBunga is enough to cover the remaining interest if ($bayarBunga>= $remaining_to_interest) {
                            $new_payment_value_interest = $res['INTEREST'];
                            $bayarBunga -= $remaining_to_interest;
                            } else {
                            $new_payment_value_interest += $bayarBunga;
                            $bayarBunga = 0; // No more bayarBunga to apply
                            }

                            if ($new_payment_value_interest !== $res['PAYMENT_VALUE_INTEREST']) {
                            $interestUpdates['PAYMENT_VALUE_INTEREST'] = $new_payment_value_interest;
                            $discountPaymentData = $this->preparePaymentData($uid, 'BAYAR_PELUNASAN_BUNGA', $new_payment_value_interest);
                            M_PaymentDetail::create($discountPaymentData);
                            }

                            // Apply discount to bunga
                            if ($remaining_discount_bunga > 0) {
                            $remaining_to_interest_for_discount = $res['INTEREST'] - $new_payment_value_interest;

                            // If the remaining discount can cover the remaining interest
                            if ($remaining_discount_bunga >= $remaining_to_interest_for_discount) {
                            $interestUpdates['DISCOUNT_INTEREST'] = $remaining_to_interest_for_discount;

                            $discountPaymentData = $this->preparePaymentData($uid, 'DISKON_BUNGA', $remaining_to_interest_for_discount);
                            M_PaymentDetail::create($discountPaymentData);

                            $new_payment_value_interest += $remaining_to_interest_for_discount; // Apply discount
                            $remaining_discount_bunga -= $remaining_to_interest_for_discount; // Reduce the discount
                            } else {
                            // If the discount can't fully cover the remaining interest
                            $interestUpdates['DISCOUNT_INTEREST'] = $remaining_discount_bunga;

                            $discountPaymentData = $this->preparePaymentData($uid, 'DISKON_BUNGA', $remaining_discount_bunga);
                            M_PaymentDetail::create($discountPaymentData);

                            $new_payment_value_interest += $remaining_discount_bunga; // Apply discount
                            $remaining_discount_bunga = 0; // No more discount left
                            }
                            }

                            // Update the record if there are any changes
                            if (!empty($interestUpdates)) {
                            $res->update($interestUpdates);
                            }

                            return [
                            'bayarBunga' => $bayarBunga,
                            'remaining_discount_bunga' => $remaining_discount_bunga
                            ];
                            }

                            function hitungBungaDenda($bayarBunga, $remaining_to_interest, $remaining_discount_bunga, $res, $uid)
                            {
                            $new_payment_value_interest = $res['PAID_INT'];
                            $interestUpdates = [];

                            // If bayarBunga is enough to cover the remaining interest
                            if ($bayarBunga >= $remaining_to_interest) {
                            $new_payment_value_interest = $res['PAST_DUE_INTRST'];
                            $bayarBunga -= $remaining_to_interest;
                            } else {
                            $new_payment_value_interest += $bayarBunga;
                            $bayarBunga = 0; // No more bayarBunga to apply
                            }

                            if ($new_payment_value_interest !== $res['PAID_INT']) {
                            $interestUpdates['PAID_INT'] = $new_payment_value_interest;
                            }

                            // Apply discount to bunga
                            if ($remaining_discount_bunga > 0) {
                            $remaining_to_interest_for_discount = $res['PAST_DUE_INTRST'] - $new_payment_value_interest;

                            // If the remaining discount can cover the remaining interest
                            if ($remaining_discount_bunga >= $remaining_to_interest_for_discount) {
                            $interestUpdates['WOFF_INT'] = $remaining_to_interest_for_discount;
                            $new_payment_value_interest += $remaining_to_interest_for_discount; // Apply discount
                            $remaining_discount_bunga -= $remaining_to_interest_for_discount; // Reduce the discount
                            } else {
                            // If the discount can't fully cover the remaining interest
                            $interestUpdates['WOFF_INT'] = $remaining_discount_bunga;
                            $new_payment_value_interest += $remaining_discount_bunga; // Apply discount
                            $remaining_discount_bunga = 0; // No more discount left
                            }
                            }

                            // Update the record if there are any changes
                            if (!empty($interestUpdates)) {
                            $res->update($interestUpdates);
                            }

                            return [
                            'bayarBunga' => $bayarBunga,
                            'remaining_discount_bunga' => $remaining_discount_bunga
                            ];
                            }

                            function proccess($request,$loan_number,$no_inv,$status,$creditSchedule){

                            $uids = [];

                            foreach ($creditSchedule as $res) {
                            $uid = Uuid::uuid7()->toString();
                            $uids[] = $uid;
                            $this->proccessPayment($request, $uid, $no_inv, $status, $res);
                            }

                            $this->creditScheculeRepayment($request, $uids, $creditSchedule);
                            $this->arrearsRepayment($request,$loan_number, $uids);
                            } 