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




        $query = "SELECT 
                        b.JENIS,
                        b.BRANCH,
                        b.BRANCH_ID,
                        b.ENTRY_DATE,
                        b.ORIGINAL_AMOUNT,
                        b.LOAN_NUM,
                        b3.NAME AS PELANGGAN,
                        b.PAYMENT_METHOD,
                        b.nama_cabang,
                        b.no_invoice,
                        b.angsuran_ke,
                        b.admin_fee,
                        b.user_id,
                        u.fullname,
                        u.position
                    FROM (
                        SELECT 
                            CASE 
                                WHEN a.ACC_KEYS LIKE '%DENDA%' THEN 'DENDA'
                                ELSE b.TITLE 
                            END AS JENIS, 
                            b.BRANCH AS BRANCH, 
                            d.ID AS BRANCH_ID, 
                            d.NAME AS nama_cabang,
                            CASE 
                                WHEN b.PAYMENT_METHOD = 'transfer' THEN DATE_FORMAT(b.AUTH_DATE, '%Y-%m-%d')
                                ELSE DATE_FORMAT(b.ENTRY_DATE, '%Y-%m-%d') 
                            END AS ENTRY_DATE, 
                            SUM(a.ORIGINAL_AMOUNT) AS ORIGINAL_AMOUNT,
                            b.LOAN_NUM,
                            b.PAYMENT_METHOD,
                            b.INVOICE AS no_invoice,
                            CASE 
                                WHEN a.ACC_KEYS LIKE '%DENDA%' THEN 'DENDA'
                                ELSE b.TITLE 
                            END AS angsuran_ke,
                            b.USER_ID AS user_id,
                            '' AS admin_fee
                        FROM 
                            payment_detail a
                        INNER JOIN payment b ON b.ID = a.PAYMENT_ID
                        LEFT JOIN arrears c ON c.ID = b.ARREARS_ID
                        LEFT JOIN branch d ON d.CODE_NUMBER = b.BRANCH
                        WHERE b.ACC_KEY in ('angsuran','angsuran_denda') 
                              AND b.STTS_RCRD = 'PAID'  
                              AND a.ACC_KEYS in ('BAYAR_POKOK','BAYAR_BUNGA','ANGSURAN_POKOK','ANGSURAN_BUNGA','BAYAR_DENDA')
                        GROUP BY 
                            CASE 
                                WHEN a.ACC_KEYS LIKE '%DENDA%' THEN 'DENDA'
                                ELSE b.TITLE 
                            END, 
                            b.BRANCH, 
                            d.ID, 
                            d.NAME, 
                            CASE 
                                WHEN b.PAYMENT_METHOD = 'transfer' THEN DATE_FORMAT(b.AUTH_DATE, '%Y-%m-%d')
                                ELSE DATE_FORMAT(b.ENTRY_DATE, '%Y-%m-%d') 
                            END, 
                            b.LOAN_NUM,
                            b.PAYMENT_METHOD,
                            b.INVOICE,
                            CASE 
                                WHEN a.ACC_KEYS LIKE '%DENDA%' THEN 'DENDA'
                                ELSE b.TITLE 
                            END,
                            b.USER_ID
                        UNION ALL
                            SELECT 
                            'PELUNASAN'AS JENIS, 
                            b.CODE_NUMBER AS BRANCH, 
                            b.ID AS BRANCH_ID, 
                            b.NAME AS nama_cabang,
                            DATE_FORMAT(a.CREATED_AT, '%Y-%m-%d') AS ENTRY_DATE, 
                            (a.JUMLAH_UANG - a.PEMBULATAN) AS ORIGINAL_AMOUNT,
                            a.LOAN_NUMBER,
                            a.METODE_PEMBAYARAN as PAYMENT_METHOD,
                            a.NO_TRANSAKSI AS no_invoice,
                            'PELUNASAN' AS angsuran_ke,
                            a.CREATED_BY AS user_id,
                            '' AS admin_fee
                        FROM kwitansi a
                        LEFT JOIN branch b on b.ID = a.BRANCH_CODE
                        WHERE a.PAYMENT_TYPE = 'pelunasan' AND a.STTS_PAYMENT = 'PAID'
                        UNION ALL
                        SELECT 
                            CASE 
                                WHEN a.PAYMENT_TYPE = 'pelunasan' THEN 'PEMBULATAN PELUNASAN'
                                ELSE 'PEMBULATAN' 
                            END AS JENIS, 
                            d.CODE_NUMBER AS BRANCH, 
                            d.ID AS BRANCH_ID, 
                            d.NAME AS nama_cabang,
                            CASE 
                                WHEN a.METODE_PEMBAYARAN = 'transfer' THEN DATE_FORMAT(b.AUTH_DATE, '%Y-%m-%d')
                                ELSE DATE_FORMAT(a.CREATED_AT, '%Y-%m-%d') 
                            END AS ENTRY_DATE, 
                            a.PEMBULATAN AS ORIGINAL_AMOUNT,
                            a.LOAN_NUMBER AS LOAN_NUM,
                            a.METODE_PEMBAYARAN,
                            a.NO_TRANSAKSI AS no_invoice,
                            'PEMBULATAN' AS angsuran_ke,
                            a.CREATED_BY AS user_id,
                            '' AS admin_fee
                        FROM kwitansi a
                        LEFT JOIN payment b ON b.INVOICE = a.NO_TRANSAKSI
                        LEFT JOIN branch d ON d.ID = a.BRANCH_CODE
                        GROUP BY 
                            CASE 
                                WHEN a.PAYMENT_TYPE = 'pelunasan' THEN 'PEMBULATAN PELUNASAN'
                                ELSE 'PEMBULATAN' 
                            END,
                            d.CODE_NUMBER, 
                            d.ID, 
                            d.NAME, 
                            CASE 
                                WHEN a.METODE_PEMBAYARAN = 'transfer' THEN DATE_FORMAT(b.AUTH_DATE, '%Y-%m-%d')
                                ELSE DATE_FORMAT(a.CREATED_AT, '%Y-%m-%d') 
                            END, 
                            a.PEMBULATAN,
                            a.LOAN_NUMBER,
                            a.METODE_PEMBAYARAN,
                            a.NO_TRANSAKSI, 
                            a.CREATED_BY 
                        UNION ALL
                        SELECT 
                            'PENCAIRAN' AS JENIS, 
                            b.CODE_NUMBER AS BRANCH,
                            b.ID AS BRANCH_ID, 
                            b.NAME AS nama_cabang,
                            DATE_FORMAT(a.CREATED_AT, '%Y-%m-%d') AS ENTRY_DATE,
                            a.PCPL_ORI AS ORIGINAL_AMOUNT,
                            a.LOAN_NUMBER AS LOAN_NUM,
                            'cash' AS PAYMENT_METHOD,
                            '' AS no_invoice,
                            '' AS angsuran_ke,
                            a.CREATED_BY AS user_id,
                            a.TOTAL_ADMIN AS admin_fee
                        FROM 
                            credit a
                        INNER JOIN branch b ON b.id = a.BRANCH
                        WHERE 
                            a.STATUS = 'A'
                        GROUP BY 
                            b.CODE_NUMBER, 
                            b.ID, 
                            b.NAME, 
                            DATE_FORMAT(a.CREATED_AT, '%Y-%m-%d'),
                            a.PCPL_ORI, 
                            a.LOAN_NUMBER, 
                            a.CREATED_BY, 
                            a.TOTAL_ADMIN
                    ) AS b
                    INNER JOIN credit b2 ON b2.LOAN_NUMBER = b.LOAN_NUM
                    INNER JOIN customer b3 ON b3.CUST_CODE = b2.CUST_CODE
                    INNER JOIN users u ON u.id = b.user_id
                    WHERE b.ENTRY_DATE BETWEEN '$request->dari' AND '$request->sampai' ";


//CreditPaymentProcess

        $checkCreditSchedule = M_CreditSchedule::where('LOAN_NUMBER', $loan_number)
            ->where(function ($query) {
                $query->where('PAID_FLAG', '')
                    ->orWhereNull('PAID_FLAG');
            })
            ->get();

        $checkArrears = M_Arrears::where('LOAN_NUMBER', $loan_number)
            ->whereIn('STATUS_REC', ['A', 'PENDING'])
            ->get();

        if ($checkCreditSchedule->isEmpty() && $checkArrears->isEmpty()) {
            $status = 'D';
            $status_rec = 'CL';
        } else {
            $status = 'A';
        }

        $cekStatusActive = $this->checkStatusCreditActive($loan_number);

        if ($cekStatusActive == 0) {
            $status = 'D';
            $status_rec = 'CL';
        } else {
            $status = 'A';
        }

        if ($check_credit) {
            $check_credit->update([
                'STATUS' => $status,
                'STATUS_REC' => $status_rec ?? 'AC',
            ]);
        }

        //LBH QUERY
        // $query = "SELECT
        //                 b.JENIS,
        //                 b.BRANCH,
        //                 b.BRANCH_ID,
        //                 b.ENTRY_DATE,
        //                 b.ORIGINAL_AMOUNT,
        //                 b.LOAN_NUM,
        //                 b3.NAME AS PELANGGAN,
        //                 b.PAYMENT_METHOD,
        //                 b.nama_cabang,
        //                 b.no_invoice,
        //                 b.angsuran_ke,
        //                 b.admin_fee,
        //                 b.user_id,
        //                 u.fullname,
        //                 u.position
        //             FROM (
        //                 SELECT
        //                     CASE
        //                         WHEN a.ACC_KEYS LIKE '%DENDA%' THEN 'DENDA'
        //                         ELSE b.TITLE
        //                     END AS JENIS,
        //                     b.BRANCH AS BRANCH,
        //                     d.ID AS BRANCH_ID,
        //                     d.NAME AS nama_cabang,
        //                     CASE
        //                         WHEN b.PAYMENT_METHOD = 'transfer' THEN DATE_FORMAT(b.AUTH_DATE, '%Y-%m-%d')
        //                         ELSE DATE_FORMAT(b.ENTRY_DATE, '%Y-%m-%d')
        //                     END AS ENTRY_DATE,
        //                     SUM(a.ORIGINAL_AMOUNT) AS ORIGINAL_AMOUNT,
        //                     b.LOAN_NUM,
        //                     b.PAYMENT_METHOD,
        //                     b.INVOICE AS no_invoice,
        //                     CASE
        //                         WHEN a.ACC_KEYS LIKE '%DENDA%' THEN 'DENDA'
        //                         ELSE b.TITLE
        //                     END AS angsuran_ke,
        //                     b.USER_ID AS user_id,
        //                     '' AS admin_fee
        //                 FROM
        //                     payment_detail a
        //                 INNER JOIN payment b ON b.ID = a.PAYMENT_ID
        //                 LEFT JOIN arrears c ON c.ID = b.ARREARS_ID
        //                 LEFT JOIN branch d ON d.CODE_NUMBER = b.BRANCH
        //                 WHERE b.ACC_KEY in ('angsuran','angsuran_denda')
        //                       AND b.STTS_RCRD = 'PAID'
        //                       AND a.ACC_KEYS in ('BAYAR_POKOK','BAYAR_BUNGA','ANGSURAN_POKOK','ANGSURAN_BUNGA','BAYAR_DENDA')
        //                 GROUP BY
        //                     CASE
        //                         WHEN a.ACC_KEYS LIKE '%DENDA%' THEN 'DENDA'
        //                         ELSE b.TITLE
        //                     END,
        //                     b.BRANCH,
        //                     d.ID,
        //                     d.NAME,
        //                     CASE
        //                         WHEN b.PAYMENT_METHOD = 'transfer' THEN DATE_FORMAT(b.AUTH_DATE, '%Y-%m-%d')
        //                         ELSE DATE_FORMAT(b.ENTRY_DATE, '%Y-%m-%d')
        //                     END,
        //                     b.LOAN_NUM,
        //                     b.PAYMENT_METHOD,
        //                     b.INVOICE,
        //                     CASE
        //                         WHEN a.ACC_KEYS LIKE '%DENDA%' THEN 'DENDA'
        //                         ELSE b.TITLE
        //                     END,
        //                     b.USER_ID
        //                 UNION ALL
        //                 SELECT
        //                     CASE
        //                         WHEN a.ACC_KEYS LIKE '%DENDA%' THEN 'DENDA PELUNASAN'
        // 						WHEN a.ACC_KEYS = 'BAYAR PELUNASAN PINALTY' THEN 'PELUNASAN PINALTY'
        //                         ELSE 'PELUNASAN'
        //                     END AS JENIS,
        //                     b.BRANCH AS BRANCH,
        //                     d.ID AS BRANCH_ID,
        //                     d.NAME AS nama_cabang,
        //                     CASE
        //                         WHEN b.PAYMENT_METHOD = 'transfer' THEN DATE_FORMAT(b.AUTH_DATE, '%Y-%m-%d')
        //                         ELSE DATE_FORMAT(b.ENTRY_DATE, '%Y-%m-%d')
        //                     END AS ENTRY_DATE,
        //                     ROUND(SUM(a.ORIGINAL_AMOUNT),2) AS ORIGINAL_AMOUNT,
        //                     b.LOAN_NUM,
        //                     b.PAYMENT_METHOD,
        //                     b.INVOICE AS no_invoice,
        //                    CASE
        //                         WHEN a.ACC_KEYS LIKE '%DENDA%' THEN 'DENDA PELUNASAN'
        // 						WHEN a.ACC_KEYS = 'BAYAR PELUNASAN PINALTY' THEN 'PELUNASAN PINALTY'
        //                         ELSE 'PELUNASAN'
        //                     END AS angsuran_ke,
        //                     b.USER_ID AS user_id,
        //                     '' AS admin_fee
        //                 FROM
        //                     payment_detail a
        //                 INNER JOIN payment b ON b.ID = a.PAYMENT_ID
        //                 LEFT JOIN arrears c ON c.ID = b.ARREARS_ID
        //                 LEFT JOIN branch d ON d.CODE_NUMBER = b.BRANCH
        //                 WHERE b.ACC_KEY like '%Pelunasan%'
        //                       AND b.STTS_RCRD = 'PAID'
        //                       AND a.ACC_KEYS in ('BAYAR_POKOK','BAYAR_BUNGA','BAYAR_DENDA','BAYAR PELUNASAN PINALTY')
        //                 GROUP BY
        //                  	CASE
        //                         WHEN a.ACC_KEYS LIKE '%DENDA%' THEN 'DENDA PELUNASAN'
        // 						WHEN a.ACC_KEYS = 'BAYAR PELUNASAN PINALTY' THEN 'PELUNASAN PINALTY'
        //                         ELSE 'PELUNASAN'
        //                     END,
        //                     b.BRANCH,
        //                     d.ID,
        //                     d.NAME,
        //                     CASE
        //                         WHEN b.PAYMENT_METHOD = 'transfer' THEN DATE_FORMAT(b.AUTH_DATE, '%Y-%m-%d')
        //                         ELSE DATE_FORMAT(b.ENTRY_DATE, '%Y-%m-%d')
        //                     END,
        //                     b.LOAN_NUM,
        //                     b.PAYMENT_METHOD,
        //                     b.INVOICE,
        //                     CASE
        //                         WHEN a.ACC_KEYS LIKE '%DENDA%' THEN 'DENDA PELUNASAN'
        //                          WHEN a.ACC_KEYS = 'BAYAR PELUNASAN PINALTY' THEN 'PELUNASAN PINALTY'
        //                         ELSE 'PELUNASAN'
        //                     END,
        //                     b.USER_ID
        //                 UNION ALL
        //                 SELECT
        //                     CASE
        //                         WHEN a.PAYMENT_TYPE = 'pelunasan' THEN 'PEMBULATAN PELUNASAN'
        //                         ELSE 'PEMBULATAN'
        //                     END AS JENIS,
        //                     d.CODE_NUMBER AS BRANCH,
        //                     d.ID AS BRANCH_ID,
        //                     d.NAME AS nama_cabang,
        //                     CASE
        //                         WHEN a.METODE_PEMBAYARAN = 'transfer' THEN DATE_FORMAT(b.AUTH_DATE, '%Y-%m-%d')
        //                         ELSE DATE_FORMAT(a.CREATED_AT, '%Y-%m-%d')
        //                     END AS ENTRY_DATE,
        //                     a.PEMBULATAN AS ORIGINAL_AMOUNT,
        //                     a.LOAN_NUMBER AS LOAN_NUM,
        //                     a.METODE_PEMBAYARAN,
        //                     a.NO_TRANSAKSI AS no_invoice,
        //                     'PEMBULATAN' AS angsuran_ke,
        //                     a.CREATED_BY AS user_id,
        //                     '' AS admin_fee
        //                 FROM kwitansi a
        //                 LEFT JOIN payment b ON b.INVOICE = a.NO_TRANSAKSI
        //                 LEFT JOIN branch d ON d.ID = a.BRANCH_CODE
        //                 WHERE a.STTS_PAYMENT = 'PAID'
        //                 GROUP BY
        //                     CASE
        //                         WHEN a.PAYMENT_TYPE = 'pelunasan' THEN 'PEMBULATAN PELUNASAN'
        //                         ELSE 'PEMBULATAN'
        //                     END,
        //                     d.CODE_NUMBER,
        //                     d.ID,
        //                     d.NAME,
        //                     CASE
        //                         WHEN a.METODE_PEMBAYARAN = 'transfer' THEN DATE_FORMAT(b.AUTH_DATE, '%Y-%m-%d')
        //                         ELSE DATE_FORMAT(a.CREATED_AT, '%Y-%m-%d')
        //                     END,
        //                     a.PEMBULATAN,
        //                     a.LOAN_NUMBER,
        //                     a.METODE_PEMBAYARAN,
        //                     a.NO_TRANSAKSI,
        //                     a.CREATED_BY
        //                 UNION ALL
        //                 SELECT
        //                     'PENCAIRAN' AS JENIS,
        //                     b.CODE_NUMBER AS BRANCH,
        //                     b.ID AS BRANCH_ID,
        //                     b.NAME AS nama_cabang,
        //                     DATE_FORMAT(a.CREATED_AT, '%Y-%m-%d') AS ENTRY_DATE,
        //                     a.PCPL_ORI AS ORIGINAL_AMOUNT,
        //                     a.LOAN_NUMBER AS LOAN_NUM,
        //                     'cash' AS PAYMENT_METHOD,
        //                     '' AS no_invoice,
        //                     '' AS angsuran_ke,
        //                     a.CREATED_BY AS user_id,
        //                     a.TOTAL_ADMIN AS admin_fee
        //                 FROM
        //                     credit a
        //                 INNER JOIN branch b ON b.id = a.BRANCH
        //                 WHERE
        //                     a.STATUS = 'A'
        //                 GROUP BY
        //                     b.CODE_NUMBER,
        //                     b.ID,
        //                     b.NAME,
        //                     DATE_FORMAT(a.CREATED_AT, '%Y-%m-%d'),
        //                     a.PCPL_ORI,
        //                     a.LOAN_NUMBER,
        //                     a.CREATED_BY,
        //                     a.TOTAL_ADMIN
        //             ) AS b
        //             INNER JOIN credit b2 ON b2.LOAN_NUMBER = b.LOAN_NUM
        //             INNER JOIN customer b3 ON b3.CUST_CODE = b2.CUST_CODE
        //             INNER JOIN users u ON u.id = b.user_id
        //             WHERE b.ENTRY_DATE BETWEEN '$request->dari' AND '$request->sampai' ";

         public function listBan(Request $request)
    {
        try {

            $dateFrom = $request->dari;
            $getBranch = $request->cabang_id;

            $query = "  SELECT
                            CONCAT(a.CODE, '-', a.CODE_NUMBER) AS KODE,
                            a.NAME AS NAMA_CABANG,
                            b.LOAN_NUMBER AS NO_KONTRAK,
                            c.NAME AS NAMA_PELANGGAN,
                            b.CREATED_AT AS TGL_BOOKING,
                            NULL AS UB,
                            NULL AS PLATFORM,
                            c.INS_ADDRESS AS ALAMAT_TAGIH,
                            c.ZIP_CODE AS KODE_POST,
                            '' AS SUB_ZIP,
                            c.PHONE_HOUSE AS NO_TELP,
                            c.PHONE_PERSONAL AS NO_HP,
                            c.PHONE_PERSONAL AS NO_HP2,
                            c.OCCUPATION AS PEKERJAAN,
                            CONCAT(h.REF_PELANGGAN, ' ', h.REF_PELANGGAN_OTHER) AS supplier,
                            coalesce(d.fullname,b.mcf_id) AS SURVEYOR,
                            f.survey_note AS CATT_SURVEY,
                            b.PCPL_ORI AS PKK_HUTANG,
                            b.PERIOD AS JUMLAH_ANGSURAN,
                            b.INSTALLMENT_COUNT/b.PERIOD AS JARAK_ANGSURAN,
                            b.INSTALLMENT_COUNT as PERIOD,
                            coalesce(i.OS_POKOK,b.PCPL_ORI) AS OUTSTANDING,
                            coalesce(i.OS_BUNGA,b.INTRST_ORI) as OS_BUNGA,
                            DATEDIFF(str_to_date('28022025','%d%m%Y'),i.TUNGGAKAN_PERTAMA) AS OVERDUE_AWAL,
                            coalesce(i.TUNGGAKAN_POKOK) as AMBC_PKK_AWAL,
                            coalesce(i.TUNGGAKAN_BUNGA) as AMBC_BNG_AWAL,
                            coalesce(i.TUNGGAKAN_POKOK)+coalesce(i.TUNGGAKAN_BUNGA) as AMBC_TOTAL_AWAL,
                            concat('C',case when date_format(b.entry_date,'%m%Y')=date_format(now(),'%m%Y') then 'N'
		                                    when date_format(case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then now() else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end,'%m%Y')=date_format(now(),'%m%Y') then '0'
		                                    when floor((DATEDIFF(str_to_date('01032025','%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then 0 else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end))/30)<0 then 'M'
                                            when (DATEDIFF(str_to_date('01032025','%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then now() else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end)) between 211 and 240 then '8'
		                                    when ceil((DATEDIFF(str_to_date('01032025','%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then 0 else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end))/30)>=8 then 'X'
                                            else ceil((DATEDIFF(str_to_date('01032025','%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then 0 else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end))/30) end) AS CYCLE_AWAL,
                            b.STATUS_REC,
                            b.STATUS_REC,
                            case when (b.INSTALLMENT_COUNT/b.PERIOD)=1 then 'BULANAN' else 'MUSIMAN' end as pola_bayar,
                            b.PCPL_ORI-b.PAID_PRINCIPAL OS_PKK_AKHIR,
                            coalesce(k.OS_BNG_AKHIR,0) as OS_BNG_AKHIR,
                            j.DUE_DAYS as OVERDUE_AKHIR,
                            b.INSTALLMENT,
                            case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then 0 else i.INST_COUNT end as LAST_INST,
                            e.INSTALLMENT_TYPE AS tipe,
                            case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then 0 else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end as F_ARR_CR_SCHEDL,
                            case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then 0 else k.F_ARR_CR_SCHEDL end as curr_arr,
                            case when date_format(l.entry_date,'%m%Y')=date_format(now(),'%m%Y') then l.entry_date else null end as LAST_PAY,
                            ' ' AS COLLECTOR,
                            l.payment_method as cara_bayar,
                            coalesce(k.AMBC_PKK_AKHIR,0) as AMBC_PKK_AKHIR,
                            coalesce(k.AMBC_BNG_AKHIR,0) as AMBC_BNG_AKHIR,
                            coalesce(k.AMBC_PKK_AKHIR,0)+coalesce(k.AMBC_BNG_AKHIR,0) as AMBC_TOTAL_AKHIR,
                            coalesce(m.BAYAR_POKOK,0) AC_PKK,
                            coalesce(m.BAYAR_BUNGA,0) AC_BNG_MRG,
                            coalesce(m.BAYAR_POKOK,0)+coalesce(m.BAYAR_BUNGA,0) AC_TOTAL,
                            concat('C',case when floor(j.DUE_DAYS/30)>8 then 'X' else floor(j.DUE_DAYS/30) end) as CYCLE_AKHIR,
                            case when (b.INSTALLMENT_COUNT/b.PERIOD)=1 then 'BULANAN' else 'MUSIMAN' end as pola_bayar_akhir,
                            'jenis jaminan',
                            g.COLLATERAL,
                            g.POLICE_NUMBER,
                            g.ENGINE_NUMBER,
                            g.CHASIS_NUMBER,
                            g.PRODUCTION_YEAR,
                            b.PCPL_ORI-b.TOTAL_ADMIN as TOTAL_PINJAMAN,
                            b.TOTAL_ADMIN,
                            b.CUST_CODE
                        FROM  	branch AS a
                            INNER JOIN credit b ON b.BRANCH = a.ID AND b.STATUS='A' OR (b.BRANCH = a.ID AND b.STATUS in ('D','S') AND b.loan_number in (select loan_num from payment where date_format(entry_date,'%m%Y')=date_format(now(),'%m%Y')))
                            LEFT JOIN customer c ON c.CUST_CODE = b.CUST_CODE
                            LEFT JOIN users d ON d.id = b.MCF_ID
                            LEFT JOIN cr_application e ON e.ORDER_NUMBER = b.ORDER_NUMBER
                            LEFT JOIN cr_order h ON h.APPLICATION_ID = e.ID
                            LEFT JOIN cr_survey f ON f.id = e.CR_SURVEY_ID
                            LEFT JOIN (	SELECT	CR_CREDIT_ID,
                                        sum(VALUE) as TOTAL_JAMINAN,
                                        GROUP_CONCAT(concat(BRAND,' ',TYPE)) as COLLATERAL,
                                        GROUP_CONCAT(POLICE_NUMBER) as POLICE_NUMBER,
                                        GROUP_CONCAT(ENGINE_NUMBER) as ENGINE_NUMBER,
                                        GROUP_CONCAT(CHASIS_NUMBER) as CHASIS_NUMBER,
                                        GROUP_CONCAT(PRODUCTION_YEAR) as PRODUCTION_YEAR
                                    FROM 	cr_collateral
                                    GROUP 	BY CR_CREDIT_ID) g ON g.CR_CREDIT_ID = b.ID
                                LEFT JOIN credit_2025 i on cast(i.loan_number as char) = cast(b.LOAN_NUMBER as char)
                                                        and i.back_date='2025-02-28'
                                LEFT JOIN first_arr j on cast(j.LOAN_NUMBER as char) = cast(b.LOAN_NUMBER as char)

                            LEFT JOIN (	SELECT	loan_number, sum(interest)-sum(coalesce(payment_value_interest,0))-sum(discount_interest) as OS_BNG_AKHIR,
				                                sum(principal)-sum(coalesce(payment_value_principal,0))-sum(discount_principal) as OS_PKK_AKHIR,
				case when count(ID)=sum(case when paid_flag='PAID' then 1 else 0 end) then ''
        				else min(case when cast(paid_flag as char)='PAID' then 999 else installment_count end) end as LAST_INST,
				max(case when cast(paid_flag as char)='PAID' then payment_date else str_to_date('01011900','%d%m%Y') end) as LAST_PAY,
				case when count(ID)=sum(case when paid_flag='PAID' then 1 else 0 end) then ''
        	 			else min(case when cast(coalesce(paid_flag,'') as char)<>'PAID' then payment_date else str_to_date('01013000','%d%m%Y') end) end as F_ARR_CR_SCHEDL,
				sum(case when payment_date < str_to_date(concat('01',date_format(date_add(now(),interval 1 month),'%m%Y')),'%d%m%Y') and paid_flag<>'PAID' then (interest-payment_value_interest-discount_interest)
            	 			else 0 end) as AMBC_BNG_AKHIR,
				sum(case when payment_date < str_to_date(concat('01',date_format(date_add(now(),interval 1 month),'%m%Y')),'%d%m%Y') and paid_flag<>'PAID' then (principal-payment_value_principal-discount_principal)
            	 			else 0 end) as AMBC_PKK_AKHIR
			FROM	credit_schedule
			WHERE	loan_number in (select loan_number from credit where status='A'
					or (status in ('S','D') and loan_number in (select loan_num from payment where date_format(entry_date,'%m%Y')=date_format(now(),'%m%Y'))))
			GROUP	BY loan_number) k on k.loan_number=b.loan_number
                            LEFT JOIN (	SELECT	loan_num, str_to_date(date_format(entry_date,'%d%m%Y'),'%d%m%Y') as entry_date,
                                                replace(replace(group_concat(payment_method),'AGENT EKS',''),',','') as payment_method,
                                                concat('Angsuran Ke-',max(cast(replace(title,'Angsuran Ke-','') as signed)))
			                            FROM	payment
			                            WHERE	(cast(loan_num as char),date_format(entry_date,'%d%m%Y %H%i')) in
                                        (select cast(s1.loan_num as char), date_format(max(s1.entry_date),'%d%m%Y %H%i')
         				                 from 	payment s1
         					                    inner join payment_detail s2 on s2.PAYMENT_ID=s1.ID and s2.ACC_KEYS in ('ANGSURAN_POKOK','BAYAR_POKOK','ANGSURAN_BUNGA')
         				                 group 	by s1.loan_num)
			                            group by loan_num, str_to_date(date_format(entry_date,'%d%m%Y'),'%d%m%Y')) l on l.loan_num=b.loan_number
                            LEFT JOIN (	SELECT	s1.LOAN_NUM,
				sum(case when s2.ACC_KEYS in ('BAYAR_POKOK','ANGSURAN_POKOK') then s2.ORIGINAL_AMOUNT else 0 end) as BAYAR_POKOK,
        			sum(case when s2.ACC_KEYS='ANGSURAN_BUNGA' then s2.ORIGINAL_AMOUNT else 0 end) as BAYAR_BUNGA
			FROM	payment s1
				inner join payment_detail s2 on s2.PAYMENT_ID=s1.ID
			WHERE	date_format(s1.ENTRY_DATE,'%m%Y')=date_format(now(),'%m%Y')
                    and s2.ACC_KEYS in ('BAYAR_POKOK','ANGSURAN_POKOK','ANGSURAN_BUNGA')
			GROUP	BY s1.LOAN_NUM) m on m.loan_num=b.loan_number
                            WHERE 1=1";

            if (!empty($getBranch) && $getBranch != 'SEMUA CABANG') {
                $query .= " AND a.ID = '$getBranch'";
            }

            $query .= " ORDER BY a.NAME, b.CREATED_AT ASC";

            $results = DB::select($query);

            $build = [];
            foreach ($results as $result) {

                $getUsers = User::find($result->SURVEYOR);

                $build[] = [
                    "KODE CABANG" => $result->KODE ?? '',
                    "NAMA CABANG" => $result->NAMA_CABANG ?? '',
                    "NO KONTRAK" =>  (string)($result->NO_KONTRAK ?? ''),
                    "NAMA PELANGGAN" => $result->NAMA_PELANGGAN ?? '',
                    "TGL BOOKING" => isset($result->TGL_BOOKING) && !empty($result->TGL_BOOKING) ? date("d-m-Y", strtotime($result->TGL_BOOKING)) : '',
                    "UB" => $result->UB ?? '',
                    "PLATFORM" => $result->PLATFORM ?? '',
                    "ALAMAT TAGIH" => $result->ALAMAT_TAGIH ?? '',
                    "KODE POS" => $result->KODE_POST ?? '',
                    "SUBZIP" => '',
                    "NO TELP" => $result->NO_TELP ?? '',
                    "NO HP1" => $result->NO_HP ?? '',
                    "NO HP2" => $result->NO_HP2 ?? '',
                    "PEKERJAAN" => $result->PEKERJAAN ?? '',
                    "SUPPLIER" => $result->supplier ?? '',
                    "SURVEYOR" => $getUsers ? $getUsers->fullname ?? '' : $result->SURVEYOR ?? '',
                    "CATT SURVEY" => $result->CATT_SURVEY ?? '',
                    "PKK HUTANG" => intval($result->PKK_HUTANG) ?? 0,
                    "JML ANGS" => $result->JUMLAH_ANGSURAN ?? '',
                    "JRK ANGS" => $result->JARAK_ANGSURAN ?? '',
                    "PERIOD" => $result->PERIOD ?? '',
                    "OUT PKK AWAL" => intval($result->OUTSTANDING) ?? 0,
                    "OUT BNG AWAL" => intval($result->OS_BUNGA) ?? 0,
                    "OVERDUE AWAL" => $result->OVERDUE_AWAL ?? 0,
                    "AMBC PKK AWAL" => intval($result->AMBC_PKK_AWAL),
                    "AMBC BNG AWAL" => intval($result->AMBC_BNG_AWAL),
                    "AMBC TOTAL AWAL" => intval($result->AMBC_TOTAL_AWAL),
                    "CYCLE AWAL" => $result->CYCLE_AWAL ?? '',
                    "STS KONTRAK" => $result->STATUS_REC ?? '',
                    "STS BEBAN" => $result->STATUS_BEBAN ?? '',
                    "POLA BYR AWAL" => $result->pola_bayar ?? '',
                    "OUTS PKK AKHIR" => $result->PAID_PRINCIPAL ?? 0,
                    "OUTS BNG AKHIR" => $result->PAID_INTEREST ?? 0,
                    "OVERDUE AKHIR" => intval($result->OUTSTANDING) ?? 0,
                    "ANGSURAN" => intval($result->INSTALLMENT) ?? 0,
                    "ANGS KE" => $result->LAST_INST ?? '',
                    "TIPE ANGSURAN" => $result->tipe ?? '',
                    "JTH TEMPO AWAL" => $result->F_ARR_CR_SCHEDL == '0' || $result->F_ARR_CR_SCHEDL == '' || $result->F_ARR_CR_SCHEDL == 'null' ? '' : date("d-m-Y", strtotime($result->F_ARR_CR_SCHEDL ?? '')),
                    "JTH TEMPO AKHIR" => $result->curr_arr == '0' || $result->curr_arr == '' || $result->curr_arr == 'null' ? '' : date("d-m-Y", strtotime($result->curr_arr ?? '')),
                    "TGL BAYAR" => $result->LAST_PAY == '0' || $result->LAST_PAY == '' || $result->LAST_PAY == 'null' ? '' : date("d-m-Y", strtotime($result->LAST_PAY ?? '')),
                    "KOLEKTOR" => $result->COLLECTOR,
                    "CARA BYR" => $result->cara_bayar,
                    "AMBC PKK_AKHIR" => intval($result->AMBC_PKK_AKHIR) ?? 0,
                    "AMBC BNG_AKHIR" => intval($result->AMBC_BNG_AKHIR) ?? 0,
                    "AMBC TOTAL_AKHIR" => intval($result->AMBC_TOTAL_AKHIR) ?? 0,
                    "AC PKK" => intval($result->AC_PKK),
                    "AC BNG MRG" => intval($result->AC_BNG_MRG),
                    "AC TOTAL" => intval($result->AC_TOTAL),
                    "CYCLE AKHIR" => $result->CYCLE_AKHIR,
                    "POLA BYR AKHIR" => $result->pola_bayar_akhir,
                    "NAMA BRG" => 'Sepeda Motor',
                    "TIPE BRG" =>  $result->COLLATERAL ?? '',
                    "NO POL" =>  $result->POLICE_NUMBER ?? '',
                    "NO MESIN" =>  $result->ENGINE_NUMBER ?? '',
                    "NO RANGKA" =>  $result->CHASIS_NUMBER ?? '',
                    "TAHUN" =>  $result->PRODUCTION_YEAR ?? '',
                    "NILAI PINJAMAN" => intval($result->TOTAL_PINJAMAN) ?? 0,
                    "ADMIN" =>  intval($result->TOTAL_ADMIN) ?? '',
                    "CUST_ID" =>  $result->CUST_CODE ?? ''
                ];
            }
            return response()->json($build, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

    public function listBanTestOLD(Request $request)
    {
        try {

            $dateFrom = $request->dari;
            $getBranch = $request->cabang_id;

            $query = "  SELECT
                            CONCAT(a.CODE, '-', a.CODE_NUMBER) AS KODE,
                            a.NAME AS NAMA_CABANG,
                            b.LOAN_NUMBER AS NO_KONTRAK,
                            c.NAME AS NAMA_PELANGGAN,
                            b.CREATED_AT AS TGL_BOOKING,
                            NULL AS UB,
                            NULL AS PLATFORM,
                            c.INS_ADDRESS AS ALAMAT_TAGIH,
                            c.ZIP_CODE AS KODE_POST,
                            '' AS SUB_ZIP,
                            c.PHONE_HOUSE AS NO_TELP,
                            c.PHONE_PERSONAL AS NO_HP,
                            c.PHONE_PERSONAL AS NO_HP2,
                            c.OCCUPATION AS PEKERJAAN,
                            CONCAT(h.REF_PELANGGAN, ' ', h.REF_PELANGGAN_OTHER) AS supplier,
                            coalesce(d.fullname,b.mcf_id) AS SURVEYOR,
                            f.survey_note AS CATT_SURVEY,
                            replace(format(b.PCPL_ORI,0),',','') AS PKK_HUTANG,
                            b.PERIOD AS JUMLAH_ANGSURAN,
                            replace(format(b.INSTALLMENT_COUNT/b.PERIOD,0),',','') AS JARAK_ANGSURAN,
                            b.INSTALLMENT_COUNT as PERIOD,
                            replace(format(coalesce(i.OS_POKOK,b.PCPL_ORI),0),',','') AS OUTSTANDING,
                            replace(format(coalesce(i.OS_BUNGA,b.INTRST_ORI),0),',','') AS OS_BUNGA,
                            case when DATEDIFF(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval -1 day),i.TUNGGAKAN_PERTAMA)<0 then 0
                                else DATEDIFF(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval -1 day),i.TUNGGAKAN_PERTAMA) end AS OVERDUE_AWAL,
                            replace(format(coalesce(i.TUNGGAKAN_POKOK),0),',','') as AMBC_PKK_AWAL,
                            replace(format(coalesce(i.TUNGGAKAN_BUNGA),0),',','') as AMBC_BNG_AWAL,
                            replace(format(coalesce(i.TUNGGAKAN_POKOK)+coalesce(i.TUNGGAKAN_BUNGA),0),',','') as AMBC_TOTAL_AWAL,
                            concat('C',case when date_format(b.entry_date,'%m%Y')='$dateFrom' then 'N'
                                when date_format(case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0
                                                    then str_to_date(concat('01','$dateFrom'),'%d%m%Y')
                                                    else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end,'%m%Y')='$dateFrom'
                                    then '0'
                                when floor((DATEDIFF(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0
                                                    then str_to_date(concat('01','$dateFrom'),'%d%m%Y')
                                                    else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end))/30)<0
                                    then 'M'
                                when floor((DATEDIFF(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0
                                                    then str_to_date(concat('01','$dateFrom'),'%d%m%Y')
                                                    else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end))/30)>=8
                                    then 'X'
                                when (DATEDIFF(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0
                                                    then str_to_date(concat('01','$dateFrom'),'%d%m%Y')
                                                    else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end)) between 211 and 240
                                    then '8'
                                else floor((DATEDIFF(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0
                                                    then 0 else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end))/30) end) AS CYCLE_AWAL,
                            b.STATUS_REC,
                            b.STATUS_REC,
                            case when (b.INSTALLMENT_COUNT/b.PERIOD)=1 then 'REGULER' else 'MUSIMAN' end as pola_bayar,
                            replace(format(b.PCPL_ORI-b.PAID_PRINCIPAL,0),',','') OS_PKK_AKHIR,
                            replace(format(coalesce(k.OS_BNG_AKHIR,0),0),',','') as OS_BNG_AKHIR,
                            j.DUE_DAYS as OVERDUE_AKHIR,
                            b.INSTALLMENT,
                            case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then 0 else k.LAST_INST end as LAST_INST,
                            e.INSTALLMENT_TYPE AS tipe,
                            case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then 0 else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end as F_ARR_CR_SCHEDL,
                            case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0 then 0 else k.F_ARR_CR_SCHEDL end as curr_arr,
                            case when date_format(l.entry_date,'%m%Y')='$dateFrom' then l.entry_date else null end as LAST_PAY,
                            ' ' AS COLLECTOR,
                            l.payment_method as cara_bayar,
                            replace(format(coalesce(k.AMBC_PKK_AKHIR,0),0),',','') as AMBC_PKK_AKHIR,
                            replace(format(coalesce(k.AMBC_BNG_AKHIR,0),0),',','') as AMBC_BNG_AKHIR,
                            replace(format(coalesce(k.AMBC_PKK_AKHIR,0)+coalesce(k.AMBC_BNG_AKHIR,0),0),',','') as AMBC_TOTAL_AKHIR,
                            replace(format(coalesce(m.BAYAR_POKOK,0),0),',','') AC_PKK,
                            replace(format(coalesce(m.BAYAR_BUNGA,0),0),',','') AC_BNG_MRG,
                            replace(format(coalesce(m.BAYAR_POKOK,0)+coalesce(m.BAYAR_BUNGA,0),0),',','') AC_TOTAL,
                            concat('C',case when date_format(b.entry_date,'%m%Y')='$dateFrom' then 'N'
                                when date_format(case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0
                                                    then str_to_date(concat('01','$dateFrom'),'%d%m%Y')
                                                    else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end,'%m%Y')='$dateFrom'
                                    then '0'
                                when floor((DATEDIFF(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0
                                                    then str_to_date(concat('01','$dateFrom'),'%d%m%Y')
                                                    else coalesce(i.TUNGGAKAN_PERTAMA,k.F_ARR_CR_SCHEDL) end))/30)<0
                                    then 'M'
                                when floor((DATEDIFF(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0
                                                    then str_to_date(concat('01','$dateFrom'),'%d%m%Y')
                                                    else k.F_ARR_CR_SCHEDL end))/30)>=8
                                    then 'X'
                                when (DATEDIFF(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0
                                                    then str_to_date(concat('01','$dateFrom'),'%d%m%Y')
                                                    else k.F_ARR_CR_SCHEDL end)) between 211 and 240
                                    then '8'
                                else floor((DATEDIFF(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),case when coalesce(i.OS_POKOK,b.PCPL_ORI)=0
                                                    then 0 else k.F_ARR_CR_SCHEDL end))/30) end) AS CYCLE_AKHIR,
                            case when (b.INSTALLMENT_COUNT/b.PERIOD)=1 then 'BULANAN' else 'MUSIMAN' end as pola_bayar_akhir,
                            'jenis jaminan',
                            g.COLLATERAL,
                            g.POLICE_NUMBER,
                            g.ENGINE_NUMBER,
                            g.CHASIS_NUMBER,
                            g.PRODUCTION_YEAR,
                            replace(format(b.PCPL_ORI-b.TOTAL_ADMIN,0),',','') as NILAI_PINJAMAN,
                            replace(format(b.TOTAL_ADMIN,0),',','') as TOTAL_ADMIN,
                            b.CUST_CODE
                        FROM  	branch AS a
                                INNER JOIN credit_log_2025 b
                                    ON b.BRANCH = a.ID
                                    AND b.STATUS='A'
                                    OR (b.BRANCH = a.ID
                                        AND b.STATUS in ('D','S')
                                        AND b.loan_number in (select loan_num from payment where date_format(entry_date,'%m%Y')='$dateFrom'))
                                LEFT JOIN customer c ON c.CUST_CODE = b.CUST_CODE
                                LEFT JOIN users d ON d.id = b.MCF_ID
                                LEFT JOIN cr_application e ON e.ORDER_NUMBER = b.ORDER_NUMBER
                                LEFT JOIN cr_order h ON h.APPLICATION_ID = e.ID
                                LEFT JOIN cr_survey f ON f.id = e.CR_SURVEY_ID	LEFT JOIN (	SELECT	CR_CREDIT_ID,
                                                                                                    sum(VALUE) as TOTAL_JAMINAN,
                                                                                                    GROUP_CONCAT(concat(BRAND,' ',TYPE)) as COLLATERAL,
                                                                                                    GROUP_CONCAT(POLICE_NUMBER) as POLICE_NUMBER,
                                                                                                    GROUP_CONCAT(ENGINE_NUMBER) as ENGINE_NUMBER,
                                                                                                    GROUP_CONCAT(CHASIS_NUMBER) as CHASIS_NUMBER,
                                                                                                    GROUP_CONCAT(PRODUCTION_YEAR) as PRODUCTION_YEAR
                                                                                            FROM 	cr_collateral
                                                                                            GROUP 	BY CR_CREDIT_ID) g ON g.CR_CREDIT_ID = b.ID
                                LEFT JOIN credit_2025 i
                                    on cast(i.loan_number as char) = cast(b.LOAN_NUMBER as char)
                                    and i.back_date = date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval -1 day)
                                LEFT JOIN first_arr j on cast(j.LOAN_NUMBER as char) = cast(b.LOAN_NUMBER as char)
                                LEFT JOIN (	SELECT	loan_number, sum(interest)-sum(coalesce(payment_value_interest,0))-sum(discount_interest) as OS_BNG_AKHIR,
                                                    sum(principal)-sum(coalesce(payment_value_principal,0))-sum(discount_principal) as OS_PKK_AKHIR,
                                                    case when count(ID)=sum(case when paid_flag='PAID' then 1 else 0 end) then ''
                                                            else min(case when cast(paid_flag as char)='PAID' then 999 else installment_count end) end as LAST_INST,
                                                    max(case when cast(paid_flag as char)='PAID' then payment_date else str_to_date('01011900','%d%m%Y') end) as LAST_PAY,
                                                    case when count(ID)=sum(case when paid_flag='PAID' then 1 else 0 end) then ''
                                                            else min(case when cast(coalesce(paid_flag,'') as char)<>'PAID' then payment_date else str_to_date('01013000','%d%m%Y') end) end as F_ARR_CR_SCHEDL,
                                                    sum(case when payment_date < date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month) and paid_flag<>'PAID' then (interest-payment_value_interest-discount_interest)
                                                                else 0 end) as AMBC_BNG_AKHIR,
                                                    sum(case when payment_date < date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month) and paid_flag<>'PAID' then (principal-payment_value_principal-discount_principal)
                                                                else 0 end) as AMBC_PKK_AKHIR
                                            FROM	credit_schedule_log_2025
                                            WHERE	back_date = date_add(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),interval -1 day)
                                                    and loan_number in (select 	loan_number
                                                                        from 	credit_log_2025
                                                                        where 	back_date = date_add(date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month),interval -1 day)
                                                                                and status='A'
                                                                                or (status in ('S','D') and loan_number in (select loan_num from payment where date_format(entry_date,'%m%Y')='$dateFrom')))
                                                                        GROUP	BY loan_number) k on k.loan_number=b.loan_number
                                LEFT JOIN (	SELECT	loan_num, str_to_date(date_format(entry_date,'%d%m%Y'),'%d%m%Y') as entry_date,
                                                    replace(replace(group_concat(payment_method),'AGENT EKS',''),',','') as payment_method
                                            FROM	payment
                                            WHERE	(cast(loan_num as char),date_format(entry_date,'%d%m%Y %H%i'),cast(title as char)) in
                                                    (select cast(s1.loan_num as char), date_format(max(s1.entry_date),'%d%m%Y %H%i'), concat('Angsuran Ke-',max(cast(replace(s1.title,'Angsuran Ke-','') as signed)))
                                                    from 	payment s1
                                                            inner join payment_detail s2
                                                                on s2.PAYMENT_ID=s1.ID
                                                                and s2.ACC_KEYS in ('ANGSURAN_POKOK','BAYAR_POKOK','ANGSURAN_BUNGA')
                                                    group 	by s1.loan_num)
                                                    and entry_date < date_add(str_to_date(concat('01','$dateFrom'),'%d%m%Y'),interval 1 month)
                                        group by loan_num, str_to_date(date_format(entry_date,'%d%m%Y'),'%d%m%Y')) l on l.loan_num=b.loan_number
                                LEFT JOIN (	SELECT	s1.LOAN_NUM,
                                                    sum(case when s2.ACC_KEYS in ('ANGSURAN_POKOK','BAYAR_POKOK') then s2.ORIGINAL_AMOUNT else 0 end) as BAYAR_POKOK,
                                                    sum(case when s2.ACC_KEYS='ANGSURAN_BUNGA' then s2.ORIGINAL_AMOUNT else 0 end) as BAYAR_BUNGA
                                            FROM	payment s1
                                                    inner join payment_detail s2 on s2.PAYMENT_ID=s1.ID
                                            WHERE	date_format(s1.ENTRY_DATE,'%m%Y')='$dateFrom'
                                                    and s2.ACC_KEYS in ('ANGSURAN_POKOK','BAYAR_POKOK','ANGSURAN_BUNGA')
                                            GROUP	BY s1.LOAN_NUM) m on m.loan_num=b.loan_number
                                WHERE 1=1";

            if (!empty($getBranch) && $getBranch != 'SEMUA CABANG') {
                $query .= " AND a.ID = '$getBranch'";
            }

            $query .= " ORDER BY a.NAME, b.CREATED_AT ASC";

            $results = DB::select($query);

            $build = [];
            foreach ($results as $result) {

                $getUsers = User::find($result->SURVEYOR);

                $build[] = [
                    "KODE CABANG" => $result->KODE ?? '',
                    "NAMA CABANG" => $result->NAMA_CABANG ?? '',
                    "NO KONTRAK" =>  (string)($result->NO_KONTRAK ?? ''),
                    "NAMA PELANGGAN" => $result->NAMA_PELANGGAN ?? '',
                    "TGL BOOKING" => isset($result->TGL_BOOKING) && !empty($result->TGL_BOOKING) ? date("d-m-Y", strtotime($result->TGL_BOOKING)) : '',
                    "UB" => $result->UB ?? '',
                    "PLATFORM" => $result->PLATFORM ?? '',
                    "ALAMAT TAGIH" => $result->ALAMAT_TAGIH ?? '',
                    "KODE POS" => $result->KODE_POST ?? '',
                    "SUBZIP" => '',
                    "NO TELP" => $result->NO_TELP ?? '',
                    "NO HP1" => $result->NO_HP ?? '',
                    "NO HP2" => $result->NO_HP2 ?? '',
                    "PEKERJAAN" => $result->PEKERJAAN ?? '',
                    "SUPPLIER" => $result->supplier ?? '',
                    "SURVEYOR" => $getUsers ? $getUsers->fullname ?? '' : $result->SURVEYOR ?? '',
                    "CATT SURVEY" => $result->CATT_SURVEY ?? '',
                    "PKK HUTANG" => intval($result->PKK_HUTANG) ?? 0,
                    "JML ANGS" => $result->JUMLAH_ANGSURAN ?? '',
                    "JRK ANGS" => $result->JARAK_ANGSURAN ?? '',
                    "PERIOD" => $result->PERIOD ?? '',
                    "OUT PKK AWAL" => intval($result->OUTSTANDING) ?? 0,
                    "OUT BNG AWAL" => intval($result->OS_BUNGA) ?? 0,
                    "OVERDUE AWAL" => $result->OVERDUE_AWAL ?? 0,
                    "AMBC PKK AWAL" => intval($result->AMBC_PKK_AWAL),
                    "AMBC BNG AWAL" => intval($result->AMBC_BNG_AWAL),
                    "AMBC TOTAL AWAL" => intval($result->AMBC_TOTAL_AWAL),
                    "CYCLE AWAL" => $result->CYCLE_AWAL ?? '',
                    "STS KONTRAK" => $result->STATUS_REC ?? '',
                    "STS BEBAN" => $result->STATUS_BEBAN ?? '',
                    "POLA BYR AWAL" => $result->pola_bayar ?? '',
                    "OUTS PKK AKHIR" => intval($result->OS_PKK_AKHIR) ?? 0,
                    "OUTS BNG AKHIR" => intval($result->OS_BNG_AKHIR) ?? 0,
                    "OVERDUE AKHIR" => intval($result->OUTSTANDING) ?? 0,
                    "ANGSURAN" => intval($result->INSTALLMENT) ?? 0,
                    "ANGS KE" => $result->LAST_INST ?? '',
                    "TIPE ANGSURAN" => $result->tipe ?? '',
                    "JTH TEMPO AWAL" => $result->F_ARR_CR_SCHEDL == '0' || $result->F_ARR_CR_SCHEDL == '' || $result->F_ARR_CR_SCHEDL == 'null' ? '' : date("d-m-Y", strtotime($result->F_ARR_CR_SCHEDL ?? '')),
                    "JTH TEMPO AKHIR" => $result->curr_arr == '0' || $result->curr_arr == '' || $result->curr_arr == 'null' ? '' : date("d-m-Y", strtotime($result->curr_arr ?? '')),
                    "TGL BAYAR" => $result->LAST_PAY == '0' || $result->LAST_PAY == '' || $result->LAST_PAY == 'null' ? '' : date("d-m-Y", strtotime($result->LAST_PAY ?? '')),
                    "KOLEKTOR" => $result->COLLECTOR,
                    "CARA BYR" => $result->cara_bayar,
                    "AMBC PKK_AKHIR" => intval($result->AMBC_PKK_AKHIR) ?? 0,
                    "AMBC BNG_AKHIR" => intval($result->AMBC_BNG_AKHIR) ?? 0,
                    "AMBC TOTAL_AKHIR" => intval($result->AMBC_TOTAL_AKHIR) ?? 0,
                    "AC PKK" => intval($result->AC_PKK),
                    "AC BNG MRG" => intval($result->AC_BNG_MRG),
                    "AC TOTAL" => intval($result->AC_TOTAL),
                    "CYCLE AKHIR" => $result->CYCLE_AKHIR,
                    "POLA BYR AKHIR" => $result->pola_bayar_akhir,
                    "NAMA BRG" => 'Sepeda Motor',
                    "TIPE BRG" =>  $result->COLLATERAL ?? '',
                    "NO POL" =>  $result->POLICE_NUMBER ?? '',
                    "NO MESIN" =>  $result->ENGINE_NUMBER ?? '',
                    "NO RANGKA" =>  $result->CHASIS_NUMBER ?? '',
                    "TAHUN" =>  $result->PRODUCTION_YEAR ?? '',
                    "NILAI PINJAMAN" => intval($result->NILAI_PINJAMAN) ?? 0,
                    "ADMIN" =>  intval($result->TOTAL_ADMIN) ?? '',
                    "CUST_ID" =>  $result->CUST_CODE ?? ''
                ];
            }
            return response()->json($build, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

     private function resourceDetail($data)
    {
        $survey_id = $data->id;
        $guarente_vehicle = M_CrGuaranteVehicle::where('CR_SURVEY_ID', $survey_id)->where(function ($query) {
            $query->whereNull('DELETED_AT')
                ->orWhere('DELETED_AT', '');
        })->get();

        $guarente_sertificat = M_CrGuaranteSertification::where('CR_SURVEY_ID', $survey_id)->where(function ($query) {
            $query->whereNull('DELETED_AT')
                ->orWhere('DELETED_AT', '');
        })->get();

        $approval_detail = M_SurveyApproval::where('CR_SURVEY_ID', $survey_id)->first();

        $arrayList = [
            'id' => $survey_id,
            'jenis_angsuran' => $data->jenis_angsuran ?? '',
            'data_order' => [
                'tujuan_kredit' => $data->tujuan_kredit,
                'plafond' => (int) $data->plafond,
                'tenor' => strval($data->tenor),
                'category' => $data->category,
                'jenis_angsuran' => $data->jenis_angsuran
            ],
            'data_nasabah' => [
                'nama' => $data->nama,
                'tgl_lahir' => is_null($data->tgl_lahir) ? null : date('Y-m-d', strtotime($data->tgl_lahir)),
                'no_hp' => $data->hp,
                'no_ktp' => $data->ktp,
                'no_kk' => $data->kk,
                'alamat' => $data->alamat,
                'rt' => $data->rt,
                'rw' => $data->rw,
                'provinsi' => $data->province,
                'kota' => $data->city,
                'kelurahan' => $data->kelurahan,
                'kecamatan' => $data->kecamatan,
                'kode_pos' => $data->zip_code
            ],
            'data_survey' => [
                'usaha' => $data->usaha,
                'sektor' => $data->sector,
                'lama_bekerja' => $data->work_period,
                'pengeluaran' => (int) $data->expenses,
                'pendapatan_pribadi' => (int) $data->income_personal,
                'pendapatan_pasangan' => (int) $data->income_spouse,
                'pendapatan_lainnya' => (int) $data->income_other,
                'tgl_survey' => is_null($data->visit_date) ? null : date('Y-m-d', strtotime($data->visit_date)),
                'catatan_survey' => $data->survey_note,
            ],
            'jaminan' => [],
            'prospect_approval' => [
                'flag_approval' => $approval_detail->ONCHARGE_APPRVL,
                'keterangan' => $approval_detail->ONCHARGE_DESCR,
                'status' => $approval_detail->APPROVAL_RESULT,
                'status_code' => $approval_detail->CODE
            ],
            "dokumen_indentitas" => $this->attachment($survey_id, "'ktp', 'kk', 'ktp_pasangan'"),
            "dokumen_pendukung" => M_CrSurveyDocument::attachmentGetAll($survey_id, ['other']) ?? null,
        ];

        foreach ($guarente_vehicle as $list) {
            $arrayList['jaminan'][] = [
                "type" => "kendaraan",
                'counter_id' => $list->HEADER_ID,
                "atr" => [
                    'id' => $list->ID,
                    'status_jaminan' => null,
                    'kondisi_jaminan' => $list->POSITION_FLAG,
                    "tipe" => $list->TYPE,
                    "merk" => $list->BRAND,
                    "tahun" => $list->PRODUCTION_YEAR,
                    "warna" => $list->COLOR,
                    "atas_nama" => $list->ON_BEHALF,
                    "no_polisi" => $list->POLICE_NUMBER,
                    "no_rangka" => $list->CHASIS_NUMBER,
                    "no_mesin" => $list->ENGINE_NUMBER,
                    "no_bpkb" => $list->BPKB_NUMBER,
                    "no_stnk" => $list->STNK_NUMBER,
                    "tgl_stnk" => $list->STNK_VALID_DATE,
                    "nilai" => (int) $list->VALUE,
                    "document" => $this->attachment_guarante($survey_id, $list->HEADER_ID, "'no_rangka', 'no_mesin', 'stnk', 'depan', 'belakang', 'kanan', 'kiri'")
                ]
            ];
        }

        foreach ($guarente_sertificat as $list) {
            $arrayList['jaminan'][] = [
                "type" => "sertifikat",
                'counter_id' => $list->HEADER_ID,
                "atr" => [
                    'id' => $list->ID,
                    'status_jaminan' => null,
                    "no_sertifikat" => $list->NO_SERTIFIKAT,
                    "status_kepemilikan" => $list->STATUS_KEPEMILIKAN,
                    "imb" => $list->IMB,
                    "luas_tanah" => $list->LUAS_TANAH,
                    "luas_bangunan" => $list->LUAS_BANGUNAN,
                    "lokasi" => $list->LOKASI,
                    "provinsi" => $list->PROVINSI,
                    "kab_kota" => $list->KAB_KOTA,
                    "kec" => $list->KECAMATAN,
                    "desa" => $list->DESA,
                    "atas_nama" => $list->ATAS_NAMA,
                    "nilai" => (int) $list->NILAI,
                    "document" => M_CrSurveyDocument::attachmentSertifikat($survey_id, $list->HEADER_ID, ['sertifikat']) ?? null,
                ]
            ];
        }

        return $arrayList;
    }

    public function attachment($survey_id, $data)
    {
        $documents = DB::select(
            "   SELECT *
                FROM cr_survey_document AS csd
                WHERE (TYPE, TIMEMILISECOND) IN (
                    SELECT TYPE, MAX(TIMEMILISECOND)
                    FROM cr_survey_document
                    WHERE TYPE IN ($data)
                        AND CR_SURVEY_ID = '$survey_id'
                    GROUP BY TYPE
                )
                ORDER BY TIMEMILISECOND DESC"
        );

        return $documents;
    }

    public function attachment_guarante($survey_id, $header_id, $data)
    {
        $documents = DB::select(
            "   SELECT *
                FROM cr_survey_document AS csd
                WHERE (TYPE, TIMEMILISECOND) IN (
                    SELECT TYPE, MAX(TIMEMILISECOND)
                    FROM cr_survey_document
                    WHERE TYPE IN ($data)
                        AND CR_SURVEY_ID = '$survey_id'
                        AND COUNTER_ID = '$header_id'
                    GROUP BY TYPE
                )
                ORDER BY TIMEMILISECOND DESC"
        );

        return $documents;
    }

    public function cancelList(Request $request)
    {
        try {
            $data = DB::table('payment_cancel_log as a')
                ->select(
                    'a.ID',
                    'a.INVOICE_NUMBER',
                    'a.REQUEST_BY',
                    'a.REQUEST_BRANCH',
                    'a.REQUEST_POSITION',
                    'a.REQUEST_DATE',
                    'a.ONCHARGE_PERSON',
                    'a.ONCHARGE_TIME',
                    'a.ONCHARGE_DESCR',
                    'a.ONCHARGE_FLAG',
                    'b.LOAN_NUMBER',
                    'b.TGL_TRANSAKSI'
                )
                ->leftJoin('kwitansi as b', 'b.NO_TRANSAKSI', '=', 'a.INVOICE_NUMBER')
                ->where(function ($query) {
                    $query->whereNull('a.ONCHARGE_PERSON')
                        ->orWhere('a.ONCHARGE_PERSON', '');
                })
                ->where(function ($query) {
                    $query->whereNull('a.ONCHARGE_TIME')
                        ->orWhere('a.ONCHARGE_TIME', '');
                })
                ->get();

            $dto = R_PaymentCancelLog::collection($data);

            return response()->json($dto, 200);
        } catch (\Exception $e) {
            ActivityLogger::logActivity($request, $e->getMessage(), 500);
            return response()->json(['message' => $e->getMessage(), "status" => 500], 500);
        }
    }

        if (isset($request->struktur) && is_array($request->struktur)) {
            foreach ($request->struktur as $res) {
                $loan_number = $res['loan_number'];
                $tgl_angsuran = Carbon::parse($res['tgl_angsuran'])->format('Y-m-d');
                $uid = Uuid::uuid7()->toString();

                M_Payment::create([
                    'ID' => $uid,
                    'ACC_KEY' =>  $res['bayar_denda'] != 0 ? 'angsuran_denda' : 'angsuran',
                    'STTS_RCRD' => 'CANCEL',
                    'INVOICE' => $getInvoice ?? '',
                    'NO_TRX' => $getInvoice ?? '',
                    'PAYMENT_METHOD' => 'transfer',
                    'BRANCH' => $getCodeBranch->CODE_NUMBER ?? '',
                    'LOAN_NUM' => $loan_number ?? '',
                    'ENTRY_DATE' => now(),
                    'TITLE' => 'Angsuran Ke-' . $res['angsuran_ke'],
                    'ORIGINAL_AMOUNT' => ($res['bayar_angsuran'] + $res['bayar_denda']),
                    'OS_AMOUNT' => $os_amount ?? 0,
                    'START_DATE' => $tgl_angsuran ?? '',
                    'END_DATE' => now(),
                    'USER_ID' => $request->user()->id ?? '',
                    'AUTH_BY' => $request->user()->fullname ?? '',
                    'AUTH_DATE' => now(),
                    'ARREARS_ID' => $res['id_arrear'] ?? '',
                    'BANK_NAME' => round(microtime(true) * 1000)
                ]);

                if (($res['installment'] != 0)) {
                    $credit_schedule = M_CreditSchedule::where([
                        'LOAN_NUMBER' => $loan_number,
                        'PAYMENT_DATE' => $tgl_angsuran
                    ])->where(function ($query) {
                        $query->where('PAID_FLAG', '!=', 'PAID')
                            ->orWhereNull('PAID_FLAG');
                    })->first();

                    $credit_schedule->update(['PAID_FLAG' => '']);
                }

                $today = date('Y-m-d');
                $daysDiff = (strtotime($today) - strtotime($tgl_angsuran)) / (60 * 60 * 24);
                $pastDuePenalty = $res['installment'] ?? 0 * ($daysDiff * 0.005);

                M_Arrears::where([
                    'LOAN_NUMBER' => $loan_number,
                    'START_DATE' => $tgl_angsuran
                ])->update([
                    'STATUS_REC' => 'A',
                    'PAST_DUE_PENALTY' => $pastDuePenalty ?? 0,
                    'UPDATED_AT' => Carbon::now()
                ]);
            }
        }

         // $checkFlag = $this->checkArrearsBalance($loan_number, $tgl_angsuran);

            // $check_arrears->update([
            //     'STATUS_REC' => $checkFlag != null && $checkFlag != 0 ? 'S' : 'A'
            // ]);

             public function checkArrearsBalance($loan_number, $setDate)
    {
        $checkFlag = DB::table('arrears')
            ->selectRaw('
            CASE 
                WHEN COALESCE(PAST_DUE_PENALTY, 0) = COALESCE(PAID_PENALTY, 0)
                THEN 1 
                ELSE 0 
            END AS check_flag
        ')
            ->where('LOAN_NUMBER', $loan_number)
            ->where('START_DATE', $setDate)
            ->first();

        if ($checkFlag) {
            return $checkFlag->check_flag;
        }

        return null;
    }

    //LKBH 
    -- SELECT  a.LOAN_NUM,
    --         DATE(a.ENTRY_DATE) AS ENTRY_DATE, 
    --         DATE(a.START_DATE) AS START_DATE,
    --         ROW_NUMBER() OVER (PARTITION BY a.START_DATE ORDER BY a.ENTRY_DATE) AS INST_COUNT_INCREMENT,
    --         a.ORIGINAL_AMOUNT,
    --         a.INVOICE,
    --         SUM(CASE WHEN b.ACC_KEYS = 'ANGSURAN_POKOK' 
    --             OR b.ACC_KEYS = 'ANGSURAN_BUNGA' 
    --             OR b.ACC_KEYS = 'BAYAR_POKOK' 
    --             OR b.ACC_KEYS = 'BAYAR_BUNGA'
    --             THEN b.ORIGINAL_AMOUNT ELSE 0 END) AS angsuran,
    --         SUM(CASE WHEN b.ACC_KEYS = 'BAYAR_DENDA' THEN b.ORIGINAL_AMOUNT ELSE 0 END) AS denda
    --     FROM payment a
    --         INNER JOIN payment_detail b ON b.PAYMENT_ID = a.id
    --     WHERE a.LOAN_NUM = '$id'
    --         AND a.STTS_RCRD = 'PAID'
    --     GROUP BY 
    --             a.LOAN_NUM,
    --             a.ENTRY_DATE, 
    --             a.START_DATE,
    --             a.ORIGINAL_AMOUNT,
    --             a.INVOICE


    // $sql = "SELECT
            //                 a.INSTALLMENT_COUNT,
            //                 a.PAYMENT_DATE,
            //                 a.PRINCIPAL,
            //                 a.INTEREST,
            //                 a.INSTALLMENT,
            //                 a.PAYMENT_VALUE_PRINCIPAL,
            //                 a.PAYMENT_VALUE_INTEREST,
            //                 a.INSUFFICIENT_PAYMENT,
            //                 a.PAYMENT_VALUE,
            //                 a.PAID_FLAG,
            //                 c.PAST_DUE_PENALTY,
            //                 c.PAID_PENALTY,
            //                 c.STATUS_REC,
            //                 mp.ENTRY_DATE,
            //                 mp.INST_COUNT_INCREMENT,
            //                 mp.ORIGINAL_AMOUNT,
            //                 mp.INVOICE,
            //                 mp.angsuran,
            //                 mp.denda,
            //                 (c.PAST_DUE_PENALTY - mp.denda) as sisa_denda,
            //                CASE
            //                     WHEN DATEDIFF(
            //                         COALESCE(DATE_FORMAT(mp.ENTRY_DATE, '%Y-%m-%d'), DATE_FORMAT(NOW(), '%Y-%m-%d')),
            //                         a.PAYMENT_DATE
            //                     ) < 0 THEN 0
            //                     ELSE DATEDIFF(
            //                         COALESCE(DATE_FORMAT(mp.ENTRY_DATE, '%Y-%m-%d'), DATE_FORMAT(NOW(), '%Y-%m-%d')),
            //                         a.PAYMENT_DATE
            //                     )
            //                 END AS OD
            //             from
            //                 credit_schedule as a
            //             left join
            //                 arrears as c
            //                 on c.LOAN_NUMBER = a.LOAN_NUMBER
            //                 and c.START_DATE = a.PAYMENT_DATE
            //             left join (
            //                     SELECT  
            // 						a.LOAN_NUM,
            // 						coalesce(c.CREATED_AT,a.ENTRY_DATE) AS ENTRY_DATE, 
            // 						DATE(a.START_DATE) AS START_DATE,
            // 						ROW_NUMBER() OVER (
            // 							PARTITION BY a.START_DATE 
            // 							ORDER BY DATE(c.CREATED_AT)
            // 						) AS INST_COUNT_INCREMENT,
            // 						a.ORIGINAL_AMOUNT,
            // 						a.INVOICE,
            // 						SUM(
            // 							CASE 
            // 								WHEN b.ACC_KEYS IN ('ANGSURAN_POKOK', 'ANGSURAN_BUNGA', 'BAYAR_POKOK', 'BAYAR_BUNGA') 
            // 								THEN b.ORIGINAL_AMOUNT 
            // 								ELSE 0 
            // 							END
            // 						) AS angsuran,
            // 						SUM(
            // 							CASE 
            // 								WHEN b.ACC_KEYS = 'BAYAR_DENDA' 
            // 								THEN b.ORIGINAL_AMOUNT 
            // 								ELSE 0 
            // 							END
            // 						) AS denda
            // 					FROM payment a
            // 					INNER JOIN payment_detail b ON b.PAYMENT_ID = a.id
            // 					LEFT JOIN kwitansi c ON c.NO_TRANSAKSI = a.INVOICE AND c.STTS_PAYMENT = 'PAID'
            // 					WHERE a.LOAN_NUM = '$id'
            // 					  AND a.STTS_RCRD = 'PAID'
            // 					GROUP BY 
            // 						a.LOAN_NUM,
            // 						c.CREATED_AT, 
            //                         a.ENTRY_DATE,
            // 						a.START_DATE,
            // 						a.ORIGINAL_AMOUNT,
            // 						a.INVOICE
            //             ) as mp
            //             on mp.LOAN_NUM = a.LOAN_NUMBER
            //             and date_format(mp.START_DATE,'%d%m%Y') = date_format(a.PAYMENT_DATE,'%d%m%Y')
            //             where
            //                 a.LOAN_NUMBER = '$id'
            //             order by a.PAYMENT_DATE,mp.ENTRY_DATE asc";

            // $sql = "SELECT 
            //             a.INSTALLMENT_COUNT,
            //             a.PAYMENT_DATE,
            //             a.PRINCIPAL,
            //             a.INTEREST,
            //             a.INSTALLMENT,
            //             a.PAYMENT_VALUE_PRINCIPAL,
            //             a.PAYMENT_VALUE_INTEREST,
            //             a.INSUFFICIENT_PAYMENT,
            //             a.PAYMENT_VALUE,
            //             a.PAID_FLAG,
            //             c.PAST_DUE_PENALTY,
            //             c.PAID_PENALTY,
            //             c.STATUS_REC,
            //             mp.ENTRY_DATE,
            //             mp.INST_COUNT_INCREMENT,
            //             mp.ORIGINAL_AMOUNT,
            //             mp.INVOICE,
            //             mp.angsuran,
            //             mp.denda,
            //             (c.PAST_DUE_PENALTY - mp.denda) as sisa_denda,
            //             ml.MIN_OD AS OD
            //         from
            //             credit_schedule as a
            //         left join
            //             arrears as c
            //             on c.LOAN_NUMBER = a.LOAN_NUMBER
            //             and c.START_DATE = a.PAYMENT_DATE
            //         left join (
            //                 SELECT  
            //                     a.LOAN_NUM,
            //                     coalesce(c.CREATED_AT,a.ENTRY_DATE) AS ENTRY_DATE, 
            //                     DATE(a.START_DATE) AS START_DATE,
            //                     ROW_NUMBER() OVER (
            //                         PARTITION BY a.START_DATE 
            //                         ORDER BY DATE(c.CREATED_AT)
            //                     ) AS INST_COUNT_INCREMENT,
            //                     a.ORIGINAL_AMOUNT,
            //                     a.INVOICE,
            //                     SUM(
            //                         CASE 
            //                             WHEN b.ACC_KEYS IN ('ANGSURAN_POKOK', 'ANGSURAN_BUNGA', 'BAYAR_POKOK', 'BAYAR_BUNGA') 
            //                             THEN b.ORIGINAL_AMOUNT 
            //                             ELSE 0 
            //                         END
            //                     ) AS angsuran,
            //                     SUM(
            //                         CASE 
            //                             WHEN b.ACC_KEYS = 'BAYAR_DENDA' 
            //                             THEN b.ORIGINAL_AMOUNT 
            //                             ELSE 0 
            //                         END
            //                     ) AS denda
            //                 FROM payment a
            //                 INNER JOIN payment_detail b ON b.PAYMENT_ID = a.id
            //                 LEFT JOIN kwitansi c ON c.NO_TRANSAKSI = a.INVOICE AND c.STTS_PAYMENT = 'PAID'
            //                 WHERE a.LOAN_NUM = '$id'
            //                 AND a.STTS_RCRD = 'PAID'
            //                 GROUP BY 
            //                     a.LOAN_NUM,
            //                     c.CREATED_AT, 
            //                     a.ENTRY_DATE,
            //                     a.START_DATE,
            //                     a.ORIGINAL_AMOUNT,
            //                     a.INVOICE
            //         ) as mp
            //         on mp.LOAN_NUM = a.LOAN_NUMBER
            //         and date_format(mp.START_DATE,'%d%m%Y') = date_format(a.PAYMENT_DATE,'%d%m%Y')
            //         left join (
            //         SELECT	a.INSTALLMENT_COUNT,
            //                 a.PAYMENT_DATE,
            //                 min(mp.angsuran),
            //                 min(CASE WHEN DATEDIFF(
            //                         COALESCE(DATE_FORMAT(mp.ENTRY_DATE, '%Y-%m-%d'), DATE_FORMAT(NOW(), '%Y-%m-%d')),
            //                         a.PAYMENT_DATE
            //                     ) < 0 THEN 0
            //                     ELSE DATEDIFF(
            //                         COALESCE(DATE_FORMAT(mp.ENTRY_DATE, '%Y-%m-%d'), DATE_FORMAT(NOW(), '%Y-%m-%d')),
            //                         a.PAYMENT_DATE
            //                     )
            //                 END) AS MIN_OD
            //         from	credit_schedule as a
            //                 left join
            //                     arrears as c
            //                     on c.LOAN_NUMBER = a.LOAN_NUMBER
            //                     and c.START_DATE = a.PAYMENT_DATE
            //                 left join (
            //                         SELECT  
            //                     a.LOAN_NUM,
            //                     coalesce(c.CREATED_AT,a.ENTRY_DATE) AS ENTRY_DATE, 
            //                     DATE(a.START_DATE) AS START_DATE,
            //                     ROW_NUMBER() OVER (
            //                         PARTITION BY a.START_DATE 
            //                         ORDER BY DATE(c.CREATED_AT)
            //                     ) AS INST_COUNT_INCREMENT,
            //                     a.ORIGINAL_AMOUNT,
            //                     a.INVOICE,
            //                     SUM(
            //                         CASE 
            //                             WHEN b.ACC_KEYS IN ('ANGSURAN_POKOK', 'ANGSURAN_BUNGA', 'BAYAR_POKOK', 'BAYAR_BUNGA') 
            //                             THEN b.ORIGINAL_AMOUNT 
            //                             ELSE 0 
            //                         END
            //                     ) AS angsuran,
            //                     SUM(
            //                         CASE 
            //                             WHEN b.ACC_KEYS = 'BAYAR_DENDA' 
            //                             THEN b.ORIGINAL_AMOUNT 
            //                             ELSE 0 
            //                         END
            //                     ) AS denda
            //                 FROM payment a
            //                 INNER JOIN payment_detail b ON b.PAYMENT_ID = a.id
            //                 LEFT JOIN kwitansi c ON c.NO_TRANSAKSI = a.INVOICE AND c.STTS_PAYMENT = 'PAID'
            //                 WHERE a.LOAN_NUM = '$id'
            //                 AND a.STTS_RCRD = 'PAID'
            //                 GROUP BY 
            //                     a.LOAN_NUM,
            //                     c.CREATED_AT, 
            //                     a.ENTRY_DATE,
            //                     a.START_DATE,
            //                     a.ORIGINAL_AMOUNT,
            //                     a.INVOICE
            //                 ) as mp
            //                     on mp.LOAN_NUM = a.LOAN_NUMBER
            //                     and date_format(mp.START_DATE,'%d%m%Y') = date_format(a.PAYMENT_DATE,'%d%m%Y')
            //         where	a.LOAN_NUMBER = '$id'
            //         group	by a.INSTALLMENT_COUNT,a.PAYMENT_DATE
            //         ) as ml on date_format(ml.PAYMENT_DATE,'%d%m%Y') = date_format(a.PAYMENT_DATE,'%d%m%Y')
            //         where a.LOAN_NUMBER = '$id'
            //         order by a.PAYMENT_DATE,mp.ENTRY_DATE asc";



//Customer 
$datas = $data->map(function ($customer) {

    $guarente_vehicle = DB::table('credit as a')
        ->leftJoin('cr_collateral as b', 'b.CR_CREDIT_ID', '=', 'a.ID')
        ->where('a.CUST_CODE', '=', $customer->CUST_CODE)
        ->where('a.CREATED_AT', '=', function ($query) {
            $query->select(DB::raw('MAX(CREATED_AT)'))
                ->from('credit');
        })
        ->select('b.*')
        ->get();

    $guarente_sertificat = DB::table('credit as a')
        ->leftJoin('cr_collateral_sertification as b', 'b.CR_CREDIT_ID', '=', 'a.ID')
        ->where('a.CUST_CODE', '=', $customer->CUST_CODE)
        ->where('a.CREATED_AT', '=', function ($query) {
            $query->select(DB::raw('MAX(CREATED_AT)'))
                ->from('credit');
        })
        ->select('b.*')
        ->get();

    $jaminan = [];

    foreach ($guarente_vehicle as $guarantee) {
        if (!empty($guarantee->ID)) {
            $jaminan[] = [
                "type" => "kendaraan",
                'counter_id' => $guarantee->HEADER_ID,
                "atr" => [
                    'id' => $guarantee->ID ?? null,
                    'status_jaminan' => null,
                    "tipe" => $guarantee->TYPE ?? null,
                    "merk" => $guarantee->BRAND ?? null,
                    "tahun" => $guarantee->PRODUCTION_YEAR ?? null,
                    "warna" => $guarantee->COLOR ?? null,
                    "atas_nama" => $guarantee->ON_BEHALF ?? null,
                    "no_polisi" => $guarantee->POLICE_NUMBER ?? null,
                    "no_rangka" => $guarantee->CHASIS_NUMBER ?? null,
                    "no_mesin" => $guarantee->ENGINE_NUMBER ?? null,
                    "no_bpkb" => $guarantee->BPKB_NUMBER ?? null,
                    "alamat_bpkb" => $guarantee->BPKB_ADDRESS ?? null,
                    "no_faktur" => $guarantee->INVOICE_NUMBER ?? null,
                    "no_stnk" => $guarantee->STNK_NUMBER ?? null,
                    "tgl_stnk" => $guarantee->STNK_VALID_DATE ?? null,
                    "nilai" => (int)($guarantee->VALUE ?? 0),
                    "document" => $this->getCollateralDocument($guarantee->ID, ['no_rangka', 'no_mesin', 'stnk', 'depan', 'belakang', 'kanan', 'kiri']),
                ]
            ];
        }
    }


    foreach ($guarente_sertificat as $list) {
        if (!empty($list->ID)) {
            $jaminan[] = [
                "type" => "sertifikat",
                'counter_id' => $list->HEADER_ID ?? null,
                "atr" => [
                    'id' => $list->ID ?? null,
                    'status_jaminan' => null,
                    "no_sertifikat" => $list->NO_SERTIFIKAT ?? null,
                    "status_kepemilikan" => $list->STATUS_KEPEMILIKAN ?? null,
                    "imb" => $list->IMB ?? null,
                    "luas_tanah" => $list->LUAS_TANAH ?? null,
                    "luas_bangunan" => $list->LUAS_BANGUNAN ?? null,
                    "lokasi" => $list->LOKASI ?? null,
                    "provinsi" => $list->PROVINSI ?? null,
                    "kab_kota" => $list->KAB_KOTA ?? null,
                    "kec" => $list->KECAMATAN ?? null,
                    "desa" => $list->DESA ?? null,
                    "atas_nama" => $list->ATAS_NAMA ?? null,
                    "nilai" => (int)$list->NILAI ?? null,
                    "document" => $this->getCollateralDocument($guarantee->ID, ['sertifikat'])
                ]
            ];
        }
    }

    return [
        'no_ktp' => $customer->ID_NUMBER ?? null,
        'no_kk' => $customer->KK_NUMBER ?? null,
        'nama' => $customer->NAME ?? null,
        'tgl_lahir' => $customer->BIRTHDATE ?? null,
        'alamat' => $customer->ADDRESS ?? null,
        'rw' => $customer->RW ?? null,
        'rt' => $customer->RT ?? null,
        'provinsi' => $customer->PROVINCE ?? null,
        'city' => $customer->CITY ?? null,
        'kecamatan' => $customer->KECAMATAN ?? null,
        'kelurahan' => $customer->KELURAHAN ?? null,
        'kode_pos' => $customer->ZIP_CODE ?? null,
        'no_hp' => $customer->PHONE_PERSONAL ?? null,
        "dokumen_indentitas" => M_CustomerDocument::where('CUSTOMER_ID', $customer->ID)->get(),
        'jaminan' => $jaminan
    ];
})->toArray();


$checkIdNumber = DB::table('credit as a')
            ->join('customer as b', 'b.CUST_CODE', '=', 'a.CUST_CODE')
            ->where('a.STATUS', 'A')
            ->where('b.ID_NUMBER', $ktp)
            ->where('a.ORDER_NUMBER', '!=', $request->order_number)
            ->count();

        $checkKkNumber = DB::table('credit as a')
            ->join('customer as b', 'b.CUST_CODE', '=', 'a.CUST_CODE')
            ->where('a.STATUS', 'A')
            ->where('b.KK_NUMBER', $kk)
            ->where('a.ORDER_NUMBER', '!=', $request->order_number)
            ->count();

        if (!isset($array_build["order_validation"])) {
            $array_build["order_validation"] = [];
        }

        // Validate KTP
        if ($checkIdNumber > 2) {
            $array_build["order_validation"][] = "KTP : No KTP {$ktp} Masih Ada yang Aktif";
        }

        // Validate KK
        if ($checkKkNumber > 2) {
            $array_build["order_validation"][] = "KK : No KK {$kk} Aktif Lebih Dari 2";
        }



        public function updateAll(Request $request)
        {
            DB::beginTransaction();
            try {
    
                $getDataVehicle = collect($request->json()->all());
    
                if ($getDataVehicle->isEmpty()) {
                    return response()->json(['error' => 'No taksasi data provided'], 400);
                }
    
                $result = DB::table('taksasi as a')
                    ->leftJoin('taksasi_price as b', 'b.taksasi_id', '=', 'a.id')
                    ->select('a.brand', 'a.code', 'a.model', 'a.descr', 'b.year', 'b.price')
                    ->orderBy('a.brand')
                    ->orderBy('a.code')
                    ->orderBy('b.year', 'asc')
                    ->get();
    
                if ($result->isNotEmpty()) {
    
                    $max = DB::table('taksasi_bak')
                        ->select(DB::raw('max(coalesce(count, 0)) as htung'))
                        ->first();
    
                    $result->map(function ($list) use ($request, $max) {
                        $log = [
                            'count' => intval($max->htung ?? 0) + 1,
                            'brand' => $list->brand,
                            'code' => $list->code,
                            'model' => $list->model,
                            'descr' => $list->descr,
                            'year' => $list->year,
                            'price' => $list->price,
                            'created_by' => $request->user()->id,
                            'created_at' => $this->timeNow
                        ];
    
                        M_TaksasiBak::create($log);
                    });
    
                    M_Taksasi::query()->delete();
                    M_TaksasiPrice::query()->delete();
                }
    
                $insertData = [];
                $dataExist = [];
    
                foreach ($vehicles as $vehicle) {
                    $uuid = Uuid::uuid7()->toString();
    
                    // Create a unique key using all relevant fields
                    $uniqueKey = $vehicle['brand'] . '-' . $vehicle['vehicle'] . '-' . $vehicle['type'] . '-' . $vehicle['model'];
    
                    // Format the price consistently
                    $formattedPrice = number_format(floatval(str_replace(',', '', $vehicle['price'] ?? '0')), 0, '.', '');
    
                    if (!isset($dataExist[$uniqueKey])) {
                        // First occurrence of this vehicle combination
                        $insertData[] = [
                            'id' => $uuid,
                            'brand' => $vehicle['brand'] ?? '',
                            'code' => $vehicle['vehicle'] ?? '',
                            'model' => $vehicle['type'] ?? '',
                            'descr' => $vehicle['model'] ?? '',
                            'year' => [
                                [
                                    'year' => $vehicle['year'] ?? '',
                                    'price' => $formattedPrice
                                ]
                            ],
                            'create_by' => $request->user()->id,
                            'create_at' => now(),
                        ];
    
                        // Store the index of this entry
                        $dataExist[$uniqueKey] = count($insertData) - 1;
                    } else {
                        // Vehicle combination already exists, add new year and price
                        $existingIndex = $dataExist[$uniqueKey];
    
                        // Check if this year entry already exists
                        $yearExists = false;
                        foreach ($insertData[$existingIndex]['year'] as $yearEntry) {
                            if ($yearEntry['year'] === $vehicle['year']) {
                                $yearExists = true;
                                break;
                            }
                        }
    
                        // Only add if this year doesn't exist yet
                        if (!$yearExists) {
                            $insertData[$existingIndex]['year'][] = [
                                'year' => $vehicle['year'] ?? '',
                                'price' => $formattedPrice
                            ];
                        }
                    }
                }
    
                if (count($insertData) > 0) {
                    foreach ($insertData as $data) {
                        M_Taksasi::insert([
                            'id' => $data['id'],
                            'brand' => $data['brand'],
                            'code' => $data['code'],
                            'model' => $data['model'],
                            'descr' => $data['descr'],
                            'create_by' => $data['create_by'],
                            'create_at' => $data['create_at'],
                        ]);
    
                        foreach ($data['year'] as $yearData) {
                            M_TaksasiPrice::insert([
                                'id' => Uuid::uuid7()->toString(),
                                'taksasi_id' => $data['id'],
                                'year' => $yearData['year'] ?? '',
                                'price' => $yearData['price'] ?? 0
                            ]);
                        }
                    }
                }
    
                DB::commit();
                return response()->json(['message' => 'updated successfully'], 200);
            } catch (\Exception $e) {
                return $this->log->logError($e, $request);
            }
        }

        // private function buildStrukturTenorsMusiman($links, $specificTenor = null, $plafond, $angsuran_type)
    // {
    //     $struktur = [];
    //     foreach ($links as $link) {
    //         $struktur[] = [
    //             'fee_name' => $link['fee_name'],
    //             '6_month' => $link['6_month'],
    //             '12_month' => $link['12_month'],
    //             '18_month' => $link['18_month'],
    //             '24_month' => $link['24_month'],
    //         ];
    //     }

    //     $tenors = $specificTenor ? [$specificTenor] : ['6', '12', '18', '24'];

    //     $strukturTenors = [];

    //     foreach ($tenors as $tenor) {
    //         $tenorData = ['tenor' => strval($tenor)];
    //         $total = 0;
    //         $tenor_name = $tenor . '_month';

    //         foreach ($struktur as $s) {
    //             $feeName = $s['fee_name'];
    //             $feeValue = (float) $s[$tenor_name];

    //             $tenorData[$feeName] = $feeValue;

    //             if ($feeName !== 'eff_rate') {
    //                 $total += $feeValue;
    //             }
    //         }  

    //         if ($angsuran_type == 'bulanan') {
    //             $set_tenor = $tenor;
    //         } else {
    //             if ($specificTenor) {
    //                 $set_tenor = $tenor;
    //             } else {
    //                 switch ($tenor) {
    //                     case '6':
    //                         $set_tenor = 3;
    //                         break;
    //                     case '12':
    //                         $set_tenor = 6;
    //                         break;
    //                     case '18':
    //                         $set_tenor = 12;
    //                         break;
    //                     case '24':
    //                         $set_tenor = 18;
    //                         break;
    //                     default:
    //                         $set_tenor = $tenor;
    //                 }
    //             }
    //         }

    //         $eff_rate = $tenorData['eff_rate'];

    //         // $flat_rate = round($this->calculate_flat_interest($set_tenor, $eff_rate, $angsuran_type), 2);
    //         $flat_rate = 0;

    //         $pokok_pembayaran = ($plafond + $total);
    //         $interest_margin = (int)(($flat_rate / 12) * $set_tenor * $pokok_pembayaran / 100);

    //         if ($angsuran_type == 'bulanan') {
    //             if (!in_array($set_tenor, ['6', '12', '18', '24'])) {
    //                 $angsuran_calc = 0;
    //             } else {
    //                 $angsuran_calc = ($pokok_pembayaran + $interest_margin) / $set_tenor;
    //             }
    //         } else {
    //             if ($set_tenor == 3 || $set_tenor == 6) {
    //                 $angsuran_calc = ($pokok_pembayaran + $interest_margin);
    //             } elseif ($set_tenor == 12) {
    //                 $angsuran_calc = ($pokok_pembayaran + $interest_margin) / 2;
    //             } elseif ($set_tenor == 18) {
    //                 $angsuran_calc = ($pokok_pembayaran + $interest_margin) / 3;
    //             }
    //         }

    //         $setAngsuran = ceil(round($angsuran_calc, 3) / 1000) * 1000;
    //         echo '<br>';
    //         \print_r($setAngsuran);

    //         $number =  $this->excelRate($set_tenor,-$setAngsuran,$pokok_pembayaran )*100;

    //         $tenorData['suku_bunga'] = round((($set_tenor * ($setAngsuran - ($pokok_pembayaran / $set_tenor))) / $pokok_pembayaran) * 100,2);
    //         $tenorData['flat_rate'] = round($number,10);
    //         $flat_rate = $number;
    //         $tenorData['eff_rate'] = round($number*12,8);
    //         $eff_rate = $tenorData['eff_rate'];
    //         $tenorData['angsuran'] = $setAngsuran;
    //         $tenorData['total'] = $total;
    //         $strukturTenors["tenor_$tenor"] = $tenorData;
    //     }

    //     return $strukturTenors;
    // }

    // private function buildStrukturTenorsSingle($links, $specificTenor = null, $plafond, $angsuran_type)
    // {
    //     $struktur = [];
    //     foreach ($links as $link) {
    //         $struktur[] = [
    //             'fee_name' => $link['fee_name'],
    //             '6_month' => $link['6_month'],
    //             '12_month' => $link['12_month'],
    //             '18_month' => $link['18_month'],
    //             '24_month' => $link['24_month'],
    //         ];
    //     }

    //     $musimanTenorMapping = [
    //         '3' => '6',
    //         '6' => '12',
    //         '12' => '18',
    //         '18' => '24'
    //     ];    

    //     $tenors = $specificTenor ? [$specificTenor] : ['6', '12', '18', '24'];

    //     $strukturTenors = [];

    //     foreach ($tenors as $tenor) {
    //         $tenorData = ['tenor' => strval($tenor)];
    //         $total = 0;
    //         if ($angsuran_type == 'musiman') {
    //             $tenor_name = isset($musimanTenorMapping[$tenor]) ? $musimanTenorMapping[$tenor] . '_month' : $tenor . '_month';
    //         } else {
    //             $tenor_name = $tenor . '_month';
    //         }

    //         foreach ($struktur as $s) {
    //             $feeName = $s['fee_name'];
    //             $feeValue = (float) $s[$tenor_name];
    //             $tenorData[$feeName] = $feeValue;

    //             if ($feeName !== 'eff_rate') {
    //                 $total += $feeValue;
    //             }
    //         }

    //         $pokok_pembayaran = ($plafond + $total);
    //         $set_tenor = ($angsuran_type == 'bulanan' || $specificTenor) ? $tenor : $musimanTenorMapping[$tenor] ?? $tenor;
    //         $eff_rate = $tenorData['eff_rate'];
    //         $flat_rate = round($this->calculate_flat_interest($set_tenor, $eff_rate, $angsuran_type), 2);


    //         $interest_margin = (int)(($flat_rate / 12) * $set_tenor * $pokok_pembayaran / 100);

    //         if ($angsuran_type == 'bulanan') {
    //             if (!in_array($set_tenor, ['6', '12', '18', '24'])) {
    //                 $angsuran_calc = 0;
    //             } else {
    //                 $angsuran_calc = ($pokok_pembayaran + $interest_margin) / $set_tenor;
    //             }
    //         } else {
    //             if ($set_tenor == 3 || $set_tenor == 6) {
    //                 $angsuran_calc = ($pokok_pembayaran + $interest_margin);
    //             } elseif ($set_tenor == 12) {
    //                 $angsuran_calc = ($pokok_pembayaran + $interest_margin) / 2;
    //             } elseif ($set_tenor == 18) {
    //                 $angsuran_calc = ($pokok_pembayaran + $interest_margin) / 3;
    //             }elseif ($set_tenor == 24) {
    //                 $angsuran_calc = ($pokok_pembayaran + $interest_margin) / 3;
    //             }
    //         }

    //         $setAngsuran = ceil(round($angsuran_calc, 3) / 1000) * 1000;
    //         $pokokPinjaman = $plafond + $total;

    //         $number =  round($this->excelRate($set_tenor,-$setAngsuran,$pokokPinjaman )*100,10);

    //         $tenorData['suku_bunga'] = round((($set_tenor * ($setAngsuran - ($pokokPinjaman / $set_tenor))) / $pokokPinjaman) * 100,2);
    //         $tenorData['flat_rate'] = round($number, 10);
    //         $tenorData['eff_rate'] = round($number * 12, 8);
    //         $tenorData['angsuran'] = ceil(round($angsuran_calc, 3) / 1000) * 1000;
    //         $tenorData['total'] = $total;
    //         $strukturTenors["tenor_$tenor"] = $tenorData;
    //     }

    //     return $strukturTenors;
    // }

    private function proccessKwitansiDetail($request, $kwitansi)
    {
        $loan_number = $request->LOAN_NUMBER;
        $no_inv = $kwitansi->NO_TRANSAKSI;

        $firstInstallment = DB::table('credit_schedule')
            ->select('INSTALLMENT_COUNT')
            ->where('LOAN_NUMBER', $loan_number)
            ->where('PAID_FLAG', 'PAID')
            ->orderBy('INSTALLMENT_COUNT', 'desc')
            ->first();

        if (!$firstInstallment) {
            $firstInstallment = DB::table('credit_schedule')
                ->select('INSTALLMENT_COUNT')
                ->where('LOAN_NUMBER', $loan_number)
                ->orderBy('INSTALLMENT_COUNT', 'asc')
                ->first();
        }

        $creditSchedule = M_CreditSchedule::where('LOAN_NUMBER', $loan_number)
            ->where('INSTALLMENT_COUNT', intval($firstInstallment->INSTALLMENT_COUNT))
            ->first();

        if ($creditSchedule) {
            M_KwitansiStructurDetail::firstOrCreate([
                'no_invoice' => $no_inv,
                'loan_number' => $creditSchedule['LOAN_NUMBER'] ?? ''
            ], [
                'key' => $creditSchedule['INSTALLMENT_COUNT'] ?? 1,
                'angsuran_ke' => $creditSchedule['INSTALLMENT_COUNT'] ?? '',
                'tgl_angsuran' => Carbon::parse($creditSchedule['PAYMENT_DATE'])->format('d-m-Y') ?? null,
                'principal' => $creditSchedule['PRINCIPAL'] ?? '',
                'interest' => $creditSchedule['INTEREST'] ?? '',
                'installment' => $creditSchedule['INSTALLMENT'] ?? '',
                'principal_remains' => $creditSchedule['PRINCIPAL_REMAINS'] ?? '',
                'principal_prev' => $creditSchedule['principal_prev'] ?? 0,
                'payment' => $creditSchedule['INSTALLMENT'] ?? '',
                'bayar_angsuran' => $request->UANG_PELANGGAN ?? '0',
                'bayar_denda' => '0',
                'total_bayar' => $request->TOTAL_BAYAR ?? '',
                'flag' => $creditSchedule['PAID_FLAG'] ?? '',
                'denda' => '0',
                'diskon_denda' => 0
            ]);
        }
    }

    private function processPokokBungaMenurun($request, $kwitansiDetail)
    {
        $loan_number = $request->LOAN_NUMBER;
        $no_inv = $kwitansiDetail->NO_TRANSAKSI;

        $kwitansi = M_Kwitansi::with(['kwitansi_structur_detail', 'branch'])->where([
            'LOAN_NUMBER' => $loan_number,
            'NO_TRANSAKSI' => $no_inv
        ])->first();

        if (!$kwitansi) return;

        $credit_schedule = M_CreditSchedule::where([
            'LOAN_NUMBER' => $loan_number,
            'INSTALLMENT_COUNT' => $kwitansi->kwitansi_structur_detail->map(function ($res) {
                return intval($res->angsuran_ke);
            })
        ])->first();

        if (!$credit_schedule) return;

        $getPrincipalPay = (float) $kwitansi->kwitansi_structur_detail->sum(function ($detail) {
            return floatval($detail->bayar_angsuran);
        });

        $uid = Uuid::uuid7()->toString();

        $paymentData = [
            'ID' => $uid,
            'ACC_KEY' => 'pokok_sebagian',
            'STTS_RCRD' => 'PAID',
            'INVOICE' => $no_inv ?? '',
            'NO_TRX' => $request->uid ?? '',
            'PAYMENT_METHOD' => $kwitansi->METODE_PEMBAYARAN ?? '',
            'BRANCH' =>  $kwitansi->branch['CODE_NUMBER'] ?? '',
            'LOAN_NUM' => $loan_number,
            'VALUE_DATE' => null,
            'ENTRY_DATE' => now(),
            'SUSPENSION_PENALTY_FLAG' => $request->penangguhan_denda ?? '',
            'TITLE' => 'Pembayaran Pokok Sebagian',
            'ORIGINAL_AMOUNT' => $getPrincipalPay,
            'OS_AMOUNT' => $os_amount ?? 0,
            'START_DATE' => $tgl_angsuran ?? null,
            'END_DATE' => now(),
            'USER_ID' => $kwitansi->CREATED_BY ?? $request->user()->id,
            'AUTH_BY' => $request->user()->fullname ?? '',
            'AUTH_DATE' => now(),
            'ARREARS_ID' => $res['id_arrear'] ?? '',
            'BANK_NAME' => round(microtime(true) * 1000)
        ];

        $existing = M_Payment::where($paymentData)->first();

        if (!$existing) {
            M_Payment::create($paymentData);
        }

        $data = $this->preparePaymentData($uid, 'ANGSURAN_POKOK', $getPrincipalPay);
        M_PaymentDetail::create($data);

        $principalPay = ($credit_schedule->PAYMENT_VALUE_PRINCIPAL + $getPrincipalPay);

        $credit_schedule->update([
            'PRINCIPAL' => $principalPay,
            'INSTALLMENT' => $principalPay + $credit_schedule->INTEREST,
            'PAYMENT_VALUE_PRINCIPAL' => $principalPay,
            'INSUFFICIENT_PAYMENT' => (floatval($credit_schedule->INTEREST) - floatval($credit_schedule->PAYMENT_VALUE_INTEREST)),
            'PAYMENT_VALUE' => (floatval($credit_schedule->PAYMENT_VALUE) + floatval($getPrincipalPay))
        ]);

        $this->addCreditPaid($loan_number, ['ANGSURAN_POKOK' => $getPrincipalPay]);

        $creditSchedulesUpdate = M_CreditSchedule::where('LOAN_NUMBER', $loan_number)
            ->where(function ($query) {
                $query->where('PAID_FLAG', '!=', 'PAID')
                    ->orWhere('PAID_FLAG', '=', '')
                    ->orWhereNull('PAID_FLAG');
            })
            ->where(function ($query) {
                $query->whereNull('PAYMENT_VALUE_PRINCIPAL')
                    ->orWhere('PAYMENT_VALUE_PRINCIPAL', '=', '');
            })
            ->orderBy('INSTALLMENT_COUNT', 'ASC')
            ->orderBy('PAYMENT_DATE', 'ASC')
            ->get();

        if ($creditSchedulesUpdate->isEmpty()) return;

        $totalSisaPokok = $creditSchedulesUpdate->sum('PRINCIPAL');

        $sisa_pokok = $totalSisaPokok - $getPrincipalPay;
        $sisa_pokok = max(0, $sisa_pokok);

        $getNewTenor = count($creditSchedulesUpdate);
        $calc = round($sisa_pokok * (3 / 100), 2);

        $data = new \stdClass();
        $data->SUBMISSION_VALUE = $sisa_pokok;
        $data->TOTAL_ADMIN = 0;
        $data->INSTALLMENT = $calc;
        $data->TENOR = $getNewTenor;
        $data->START_FROM = $creditSchedulesUpdate->first()->INSTALLMENT_COUNT;

        $data_credit_schedule = $this->generateAmortizationScheduleBungaMenurun($data);

        foreach ($creditSchedulesUpdate as $index => $schedule) {
            $updateData = $data_credit_schedule[$index];

            $updateArray = [
                'PRINCIPAL' => $updateData['pokok'],
                'INTEREST' => $updateData['bunga'],
                'INSTALLMENT' => $updateData['total_angsuran'],
                'PRINCIPAL_REMAINS' => $updateData['baki_debet'],
            ];

            if ((float)$updateData['total_angsuran'] == 0) {
                $updateArray['PAID_FLAG'] = 'PAID';
            }

            $schedule->update($updateArray);
        }

        $totalInterest = M_CreditSchedule::where('LOAN_NUMBER', $loan_number)
            ->where(function ($query) {
                $query->where('PAID_FLAG', '')
                    ->orWhereNull('PAID_FLAG');
            })
            ->sum('INTEREST');

        $check_credit = M_Credit::where(['LOAN_NUMBER' => $loan_number])->first();

        $check_credit->update([
            'INTRST_ORI' => $totalInterest ?? 0
        ]);
    }

    private function generateAmortizationScheduleBungaMenurun($data)
    {
        $schedule = [];
        $ttal_bayar = ($data->SUBMISSION_VALUE + $data->TOTAL_ADMIN);
        $angsuran_bunga = $data->INSTALLMENT;
        $term = ceil($data->TENOR);
        $baki_debet = $ttal_bayar;

        $startInstallment = $data->START_FROM ?? 1;

        for ($i = 0; $i < $term; $i++) {
            $pokok = 0;

            if ($i == $term - 1) {
                $pokok = $ttal_bayar;
            }

            $total_angsuran = $pokok + $angsuran_bunga;

            $schedule[] = [
                'angsuran_ke' => $startInstallment + $i,
                'baki_debet_awal' => floatval($baki_debet),
                'pokok' => floatval($pokok),
                'bunga' => floatval($angsuran_bunga),
                'total_angsuran' => floatval($total_angsuran),
                'baki_debet' => floatval($baki_debet - $pokok)
            ];

            $baki_debet -= $pokok;
        }

        return $schedule;
    }

    private function preparePaymentData($payment_id, $acc_key, $amount)
    {
        return [
            'PAYMENT_ID' => $payment_id,
            'ACC_KEYS' => $acc_key,
            'ORIGINAL_AMOUNT' => $amount
        ];
    }

    private function addCreditPaid($loan_number, array $data)
    {
        $check_credit = M_Credit::where(['LOAN_NUMBER' => $loan_number])->first();

        if ($check_credit) {
            $paidPrincipal = isset($data['ANGSURAN_POKOK']) ? $data['ANGSURAN_POKOK'] : 0;
            $paidInterest = isset($data['ANGSURAN_BUNGA']) ? $data['ANGSURAN_BUNGA'] : 0;
            $paidPenalty = isset($data['BAYAR_DENDA']) ? $data['BAYAR_DENDA'] : 0;

            $check_credit->update([
                'PAID_PRINCIPAL' => floatval($check_credit->PAID_PRINCIPAL) + floatval($paidPrincipal),
                'PAID_INTEREST' => floatval($check_credit->PAID_INTEREST) + floatval($paidInterest),
                'PAID_PENALTY' => floatval($check_credit->PAID_PENALTY) + floatval($paidPenalty)
            ]);
        }
    }