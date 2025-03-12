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



                            static function queryMenu($req)
    {
        $menuItems = self::query()
            ->select('master_menu.*')
            ->join('master_users_access_menu as t1', 'master_menu.id', '=', 't1.master_menu_id')
            ->where('t1.users_id', $req->user()->id)
            ->where('master_menu.deleted_by', null)
            ->whereIn('master_menu.status', ['active', 'Active'])
            ->get();

        return $menuItems;
    }
    static function buildMenuArray($menuItems)
    {
        $listMenu = self::queryMenu($menuItems);
        $menuArray = [];
        $homeParent = null;

        // Find the 'home' parent menu item
        foreach ($listMenu as $menuItem) {
            if ($menuItem->menu_name === 'home' && $menuItem->parent === null) {
                $homeParent = $menuItem;
                break;
            }
        }

        // Initialize the 'home' parent menu in the array
        if ($homeParent) {
            $menuArray[$homeParent->id] = [
                'menuid' => $homeParent->id,
                'menuitem' => [
                    'labelmenu' => $homeParent->menu_name,
                    'routename' => $homeParent->route,
                    'leading' => explode(',', $homeParent->leading),
                    'action' => $homeParent->action,
                    'ability' => $homeParent->ability,
                    'submenu' => []
                ]
            ];
        }

        // Process each menu item to build the menu hierarchy
        foreach ($listMenu as $menuItem) {
            if ($menuItem->parent === null || $menuItem->parent === 0) {
                // If the item has no parent, add it as a root item
                if (!isset($menuArray[$menuItem->id])) {
                    $menuArray[$menuItem->id] = [
                        'menuid' => $menuItem->id,
                        'menuitem' => [
                            'labelmenu' => $menuItem->menu_name,
                            'routename' => $menuItem->route,
                            'leading' => explode(',', $menuItem->leading),
                            'action' => $menuItem->action,
                            'ability' => $menuItem->ability,
                            'submenu' => self::buildSubMenu($menuItem->id, $listMenu)
                        ]
                    ];
                }
            } else {
                // Initialize the parent item if not set
                if (!isset($menuArray[$menuItem->parent])) {
                    $parentMenuItem = M_MasterMenu::find($menuItem->parent);
                    if ($parentMenuItem) {
                        $menuArray[$menuItem->parent] = [
                            'menuid' => $parentMenuItem->id,
                            'menuitem' => [
                                'labelmenu' => $parentMenuItem->menu_name,
                                'routename' => $parentMenuItem->route,
                                'leading' => explode(',', $parentMenuItem->leading),
                                'action' => $parentMenuItem->action,
                                'ability' => $parentMenuItem->ability,
                                'submenu' => []
                            ]
                        ];
                    }
                }

                // Add the current item as a submenu of its parent
                if (!self::menuItemExists($menuArray[$menuItem->parent]['menuitem']['submenu'], $menuItem->id)) {
                    $menuArray[$menuItem->parent]['menuitem']['submenu'][] = [
                        'subid' => $menuItem->id,
                        'sublabel' => $menuItem->menu_name,
                        'subroute' => $menuItem->route,
                        'leading' => explode(',', $menuItem->leading),
                        'action' => $menuItem->action,
                        'ability' => $menuItem->ability,
                        'submenu' => self::buildSubMenu($menuItem->id, $listMenu)
                    ];
                }
            }
        }

        // Re-index submenu arrays for each menu item
        foreach ($menuArray as $key => $menu) {
            $menuArray[$key]['menuitem']['submenu'] = array_values($menu['menuitem']['submenu']);
        }

        return array_values($menuArray);
    }

    private static function buildSubMenu($parentId, $menuItems)
    {
        $submenuArray = [];
        foreach ($menuItems as $menuItem) {
            if ($menuItem->parent === $parentId) {
                if (!self::menuItemExists($submenuArray, $menuItem->id)) {
                    $submenuArray[] = [
                        'subid' => $menuItem->id,
                        'sublabel' => $menuItem->menu_name,
                        'subroute' => $menuItem->route,
                        'leading' => explode(',', $menuItem->leading),
                        'action' => $menuItem->action,
                        'ability' => $menuItem->ability,
                        'submenu' => self::buildSubMenu($menuItem->id, $menuItems)
                    ];
                }
            }
        }
        return $submenuArray;
    }

    private static function menuItemExists($menuArray, $id)
    {
        foreach ($menuArray as $menuItem) {
            if ($menuItem['subid'] == $id) {
                return true;
            }
        }
        return false;
    }



        // $no_kontrak = $request->query('no_kontrak');
        // $atas_nama = $request->query('atas_nama');
        // $no_polisi = $request->query('no_polisi');
        // $no_bpkb = $request->query('no_bpkb');

        // $collateral = DB::table('credit as a')
        //     ->join('cr_collateral as b', 'b.CR_CREDIT_ID', '=', 'a.ID')
        //     ->where(function ($query) {
        //         $query->whereNull('b.DELETED_AT')
        //             ->orWhere('b.DELETED_AT', '!=', '');
        //     })
        //     ->where('a.STATUS', 'A')
        //     ->select(
        //         'a.LOAN_NUMBER',
        //         'b.ID',
        //         'b.BRAND',
        //         'b.TYPE',
        //         'b.PRODUCTION_YEAR',
        //         'b.COLOR',
        //         'b.ON_BEHALF',
        //         'b.ENGINE_NUMBER',
        //         'b.POLICE_NUMBER',
        //         'b.CHASIS_NUMBER',
        //         'b.BPKB_ADDRESS',
        //         'b.BPKB_NUMBER',
        //         'b.STNK_NUMBER',
        //         'b.INVOICE_NUMBER',
        //         'b.STNK_VALID_DATE',
        //         'b.VALUE'

        //     );

        // if (!empty($no_kontrak)) {
        //     $collateral->where('a.LOAN_NUMBER', $no_kontrak);
        // }

        // if (!empty($atas_nama)) {
        //     $collateral->where('b.ON_BEHALF', 'like', '%' . $atas_nama . '%');
        // }

        // if (!empty($no_polisi)) {
        //     $collateral->where('b.POLICE_NUMBER', 'like', '%' . $no_polisi . '%');
        // }

        // if (!empty($no_bpkb)) {
        //     $collateral->where('b.BPKB_NUMBER', 'like', '%' . $no_bpkb . '%');
        // }

        // $collateral->orderBy('a.CREATED_AT', 'DESC');

        // // Limit the result to 10 records
        // $collateral->limit(10);

        // $collateralData = []; // Initialize an empty array to store the results

        // // Fetch the collateral data
        // $collateralResults = $collateral->get(); // Call get() once

        // // Check if data exists
        // if ($collateralResults->isNotEmpty()) {
        //     foreach ($collateralResults as $value) {
        //         $collateralData[] = [  // Append each item to the array
        //             'loan_number'       => $value->LOAN_NUMBER,
        //             'id'                => $value->ID,
        //             'merk'              => $value->BRAND,
        //             'tipe'              => $value->TYPE,
        //             'tahun'             => $value->PRODUCTION_YEAR,
        //             'warna'             => $value->COLOR,
        //             'atas_nama'         => $value->ON_BEHALF,
        //             'no_polisi'         => $value->POLICE_NUMBER,
        //             'no_mesin'          => $value->ENGINE_NUMBER,
        //             'no_rangka'         => $value->CHASIS_NUMBER,
        //             'BPKB_ADDRESS'      => $value->BPKB_ADDRESS,
        //             'no_bpkb'           => $value->BPKB_NUMBER,
        //             'no_stnk'           => $value->STNK_NUMBER,
        //             'no_faktur'         => $value->INVOICE_NUMBER,
        //             'tgl_stnk'          => $value->STNK_VALID_DATE,
        //             'nilai'             => $value->VALUE,
        //             'asal_lokasi'       => $value->VALUE
        //         ];
        //     }
        // }

        // return response()->json($collateralResults, 200);




        $sql = "SELECT
                            a.INSTALLMENT_COUNT,
                            a.PAYMENT_DATE,
                            a.PRINCIPAL,
                            a.INTEREST,
                            a.INSTALLMENT,
                            a.PAYMENT_VALUE_PRINCIPAL,
                            a.PAYMENT_VALUE_INTEREST,
                            a.INSUFFICIENT_PAYMENT,
                            a.PAYMENT_VALUE,
                            a.PAID_FLAG,
                            c.PAST_DUE_PENALTY,
                            c.PAID_PENALTY,
                            c.STATUS_REC,
                            mp.ENTRY_DATE,
                            mp.INST_COUNT_INCREMENT,
                            mp.ORIGINAL_AMOUNT,
                            mp.INVOICE,
                            mp.angsuran,
                            mp.denda,
                            (c.PAST_DUE_PENALTY - mp.denda) as sisa_denda,
                           CASE
                                WHEN DATEDIFF(
                                    COALESCE(DATE_FORMAT(mp.ENTRY_DATE, '%Y-%m-%d'), DATE_FORMAT(NOW(), '%Y-%m-%d')),
                                    a.PAYMENT_DATE
                                ) < 0 THEN 0
                                ELSE DATEDIFF(
                                    COALESCE(DATE_FORMAT(mp.ENTRY_DATE, '%Y-%m-%d'), DATE_FORMAT(NOW(), '%Y-%m-%d')),
                                    a.PAYMENT_DATE
                                )
                            END AS OD
                        from
                            credit_schedule as a
                        left join
                            arrears as c
                            on c.LOAN_NUMBER = a.LOAN_NUMBER
                            and c.START_DATE = a.PAYMENT_DATE
                        left join (
                            SELECT  
                                a.LOAN_NUM,
                                DATE(a.ENTRY_DATE) AS ENTRY_DATE, 
                                DATE(a.START_DATE) AS START_DATE,
                                ROW_NUMBER() OVER (PARTITION BY a.START_DATE ORDER BY a.ENTRY_DATE) AS INST_COUNT_INCREMENT,
                                a.ORIGINAL_AMOUNT,
                                a.INVOICE,
                                b.angsuran,
                                b.denda
                            FROM 
                                payment a
                            LEFT JOIN (
                                SELECT  
                                    payment_id, 
                                    SUM(CASE WHEN ACC_KEYS = 'ANGSURAN_POKOK' 
                                                OR ACC_KEYS = 'ANGSURAN_BUNGA' 
                                                OR ACC_KEYS = 'BAYAR_POKOK' 
                                                OR ACC_KEYS = 'BAYAR_BUNGA'
                                                THEN ORIGINAL_AMOUNT ELSE 0 END) AS angsuran,
                                    SUM(CASE WHEN ACC_KEYS = 'BAYAR_DENDA' THEN ORIGINAL_AMOUNT ELSE 0 END) AS denda
                                FROM 
                                    payment_detail 
                                GROUP BY payment_id
                            ) AS b 
                            ON b.payment_id = a.id
                            WHERE a.LOAN_NUM = '$id'
                            AND a.STTS_RCRD = 'PAID'
                        ) as mp
                        on mp.LOAN_NUM = a.LOAN_NUMBER
                        and date_format(mp.START_DATE,'%d%m%Y') = date_format(a.PAYMENT_DATE,'%d%m%Y')
                        where
                            a.LOAN_NUMBER = '$id'
                        order by a.PAYMENT_DATE,mp.ENTRY_DATE asc";