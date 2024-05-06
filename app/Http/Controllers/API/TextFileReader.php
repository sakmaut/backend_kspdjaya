<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SlikApproval\M_SlikAgunan;
use App\Models\SlikApproval\M_SlikDebitur;
use App\Models\SlikApproval\M_SlikFasilitas;
use App\Models\SlikApproval\M_SlikPenjamin;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class TextFileReader extends Controller
{

    public function uploadText(Request $req)
    {
        DB::beginTransaction();
        try {
            $getFile = file_get_contents($req->txt_file);
            $fileContents = mb_convert_encoding($getFile, 'UTF-8', 'ISO-8859-1');
            $convert = json_decode($fileContents);

            $slik_number = self::slik_counter($req);

            self::insert_slik_debitur($convert, $slik_number);
            self::insert_slik_fasilitas($convert, $slik_number);

            DB::commit();
            return response()->json(['message' => 'File upload successfully',"status" => 200], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage(),"status" => 500], 500);
        } 
    }

    private function insert_slik_debitur($array_data, $slik_number){
        $slik_debitur = $array_data->individual->dataPokokDebitur;

        foreach ($slik_debitur as $value) {
            $data_array = [
                'ID' => Uuid::uuid4()->toString(),
                'SLIK_APPROVAL_ID' => '',
                'SLIK_NUMBER' => $slik_number,
                "namaDebitur"=> $value->namaDebitur,
                "identitas"=> $value->identitas,
                "noIdentitas"=> $value->noIdentitas,
                "alamat"=> $value->alamat,
                "jenisKelamin"=> $value->jenisKelamin,
                "jenisKelaminKet"=> $value->jenisKelaminKet,
                "npwp"=> $value->npwp,
                "tempatLahir"=> $value->tempatLahir,
                "tanggalLahir"=> $value->tanggalLahir,
                "pelapor"=> $value->pelapor,
                "pelaporKet"=> $value->pelaporKet,
                "tanggalDibentuk"=> $value->tanggalDibentuk,
                "tanggalUpdate"=> $value->tanggalUpdate,
                "kelurahan"=> $value->kelurahan,
                "kecamatan"=> $value->kecamatan,
                "kabKota"=> $value->kabKota,
                "kabKotaKet"=> $value->kabKotaKet,
                "kodePos"=> $value->kodePos,
                "negara"=> $value->negara,
                "negaraKet"=> $value->negaraKet,
                "pekerjaan"=> $value->pekerjaan,
                "pekerjaanKet"=> $value->pekerjaanKet,
                "tempatBekerja"=> $value->tempatBekerja,
                "bidangUsaha"=> $value->bidangUsaha,
                "bidangUsahaKet"=> $value->bidangUsahaKet,
                "kodeGelarDebitur"=> $value->kodeGelarDebitur,
                "statusGelarDebitur"=> $value->statusGelarDebitur
            ];
            M_SlikDebitur::create($data_array);
        }
    }

    private function insert_slik_fasilitas($array_data, $slik_number){
        $slik_fasilitas = $array_data->individual->fasilitas->kreditPembiayan;

        foreach ($slik_fasilitas as $value) {

            $slik_fasilitas_id = Uuid::uuid4()->toString();

            $data_array = [
                'ID' => $slik_fasilitas_id,
                'SLIK_APPROVAL_ID' => '',
                'SLIK_NUMBER' => $slik_number,
                "ljk" => $value->ljk,
                "ljkKet" => $value->ljkKet,
                "cabang" => $value->cabang,
                "cabangKet" => $value->cabangKet,
                "bakiDebet" => $value->bakiDebet,
                "tanggalDibentuk" => $value->tanggalDibentuk,
                "tanggalUpdate" =>  $value->tanggalUpdate == ""? null : date('Y-m-d',strtotime($value->tanggalUpdate)),
                "bulan" => $value->bulan,
                "tahun" => $value->tahun,
                "sifatKreditPembiayaan" => $value->sifatKreditPembiayaan,
                "sifatKreditPembiayaanKet" => $value->sifatKreditPembiayaanKet,
                "jenisKreditPembiayaan" => $value->jenisKreditPembiayaan,
                "jenisKreditPembiayaanKet" => $value->jenisKreditPembiayaanKet,
                "akadKreditPembiayaan" => $value->akadKreditPembiayaan,
                "akadKreditPembiayaanKet" => $value->akadKreditPembiayaanKet,
                "noRekening" => $value->noRekening,
                "frekPerpjganKreditPembiayaan" => $value->frekPerpjganKreditPembiayaan,
                "noAkadAwal" => $value->noAkadAwal,
                "tanggalAkadAwal" => $value->tanggalAkadAwal,
                "noAkadAkhir" => $value->noAkadAkhir,
                "tanggalAkadAkhir" => $value->tanggalAkadAkhir,
                "tanggalAwalKredit" => $value->tanggalAwalKredit,
                "tanggalMulai" => $value->tanggalMulai,
                "tanggalJatuhTempo" => $value->tanggalJatuhTempo,
                "kategoriDebiturKode" => $value->kategoriDebiturKode,
                "kategoriDebiturKet" => $value->kategoriDebiturKet,
                "jenisPenggunaan" => $value->jenisPenggunaan,
                "jenisPenggunaanKet" => $value->jenisPenggunaanKet,
                "sektorEkonomi" => $value->sektorEkonomi,
                "sektorEkonomiKet" => $value->sektorEkonomiKet,
                "kreditProgramPemerintah" => $value->kreditProgramPemerintah,
                "kreditProgramPemerintahKet" => $value->kreditProgramPemerintahKet,
                "lokasiProyek" => $value->lokasiProyek,
                "lokasiProyekKet" => $value->lokasiProyekKet,
                "valutaKode" => $value->valutaKode,
                "sukuBungaImbalan" => $value->sukuBungaImbalan,
                "jenisSukuBungaImbalan" => $value->jenisSukuBungaImbalan,
                "jenisSukuBungaImbalanKet" => $value->jenisSukuBungaImbalanKet,
                "kualitas" => $value->kualitas,
                "kualitasKet" => $value->kualitasKet,
                "jumlahHariTunggakan" => $value->jumlahHariTunggakan,
                "nilaiProyek" => floatval($value->nilaiProyek),
                "plafonAwal" => floatval($value->plafonAwal),
                "plafon" => floatval( $value->plafon),
                "realisasiBulanBerjalan" => floatval($value->realisasiBulanBerjalan),
                "nilaiDalamMataUangAsal" => $value->nilaiDalamMataUangAsal,
                "kodeSebabMacet" => $value->kodeSebabMacet,
                "sebabMacetKet" => $value->sebabMacetKet,
                "tanggalMacet" => $value->tanggalMacet == ""? null : date('Y-m-d',strtotime($value->tanggalMacet)),
                "tunggakanPokok" => $value->tunggakanPokok,
                "tunggakanBunga" => $value->tunggakanBunga,
                "frekuensiTunggakan" => $value->frekuensiTunggakan,
                "denda" => $value->denda,
                "frekuensiRestrukturisasi" => $value->frekuensiRestrukturisasi,
                "tanggalRestrukturisasiAkhir" => $value->tanggalRestrukturisasiAkhir == ""? null : date('Y-m-d',strtotime($value->tanggalRestrukturisasiAkhir)),
                "kodeCaraRestrukturisasi" => $value->kodeCaraRestrukturisasi,
                "restrukturisasiKet" => $value->restrukturisasiKet,
                "kondisi" => $value->kondisi,
                "kondisiKet" => $value->kondisiKet,
                "tanggalKondisi" => $value->tanggalKondisi == ""? null : date('Y-m-d',strtotime($value->tanggalKondisi)),
                "keterangan" => $value->keterangan,
                "tahunBulan01Ht" => $value->tahunBulan01Ht,
                "tahunBulan01" => $value->tahunBulan01,
                "tahunBulan01Kol" => $value->tahunBulan01Kol,
                "tahunBulan02Ht" => $value->tahunBulan02Ht,
                "tahunBulan02" => $value->tahunBulan02,
                "tahunBulan02Kol" => $value->tahunBulan02Kol,
                "tahunBulan03Ht" => $value->tahunBulan03Ht,
                "tahunBulan03" => $value->tahunBulan03,
                "tahunBulan03Kol" => $value->tahunBulan03Kol,
                "tahunBulan04Ht" => $value->tahunBulan04Ht,
                "tahunBulan04" => $value->tahunBulan04,
                "tahunBulan04Kol" => $value->tahunBulan04Kol,
                "tahunBulan05Ht" => $value->tahunBulan05Ht,
                "tahunBulan05" => $value->tahunBulan05,
                "tahunBulan05Kol" => $value->tahunBulan05Kol,
                "tahunBulan06Ht" => $value->tahunBulan06Ht,
                "tahunBulan06" => $value->tahunBulan06,
                "tahunBulan06Kol" => $value->tahunBulan06Kol,
                "tahunBulan07Ht" => $value->tahunBulan07Ht,
                "tahunBulan07" => $value->tahunBulan07,
                "tahunBulan07Kol" => $value->tahunBulan07Kol,
                "tahunBulan08Ht" => $value->tahunBulan08Ht,
                "tahunBulan08" => $value->tahunBulan08,
                "tahunBulan08Kol" => $value->tahunBulan08Kol,
                "tahunBulan09Ht" => $value->tahunBulan09Ht,
                "tahunBulan09" => $value->tahunBulan09,
                "tahunBulan09Kol" => $value->tahunBulan09Kol,
                "tahunBulan10Ht" => $value->tahunBulan10Ht,
                "tahunBulan10" => $value->tahunBulan10,
                "tahunBulan10Kol" => $value->tahunBulan10Kol,
                "tahunBulan11Ht" => $value->tahunBulan11Ht,
                "tahunBulan11" => $value->tahunBulan11,
                "tahunBulan11Kol" => $value->tahunBulan11Kol,
                "tahunBulan12Ht" => $value->tahunBulan12Ht,
                "tahunBulan12" => $value->tahunBulan12,
                "tahunBulan12Kol" => $value->tahunBulan12Kol,
                "tahunBulan13Ht" => $value->tahunBulan13Ht,
                "tahunBulan13" => $value->tahunBulan13,
                "tahunBulan13Kol" => $value->tahunBulan13Kol,
                "tahunBulan14Ht" => $value->tahunBulan14Ht,
                "tahunBulan14" => $value->tahunBulan14,
                "tahunBulan14Kol" => $value->tahunBulan14Kol,
                "tahunBulan15Ht" => $value->tahunBulan15Ht,
                "tahunBulan15" => $value->tahunBulan15,
                "tahunBulan15Kol" => $value->tahunBulan15Kol,
                "tahunBulan16Ht" => $value->tahunBulan16Ht,
                "tahunBulan16" => $value->tahunBulan16,
                "tahunBulan16Kol" => $value->tahunBulan16Kol,
                "tahunBulan17Ht" => $value->tahunBulan17Ht,
                "tahunBulan17" => $value->tahunBulan17,
                "tahunBulan17Kol" => $value->tahunBulan17Kol,
                "tahunBulan18Ht" => $value->tahunBulan18Ht,
                "tahunBulan18" => $value->tahunBulan18,
                "tahunBulan18Kol" => $value->tahunBulan18Kol,
                "tahunBulan19Ht" => $value->tahunBulan19Ht,
                "tahunBulan19" => $value->tahunBulan19,
                "tahunBulan19Kol" => $value->tahunBulan19Kol,
                "tahunBulan20Ht" => $value->tahunBulan20Ht,
                "tahunBulan20" => $value->tahunBulan20,
                "tahunBulan20Kol" => $value->tahunBulan20Kol,
                "tahunBulan21Ht" => $value->tahunBulan21Ht,
                "tahunBulan21" => $value->tahunBulan21,
                "tahunBulan21Kol" => $value->tahunBulan21Kol,
                "tahunBulan22Ht" => $value->tahunBulan22Ht,
                "tahunBulan22" => $value->tahunBulan22,
                "tahunBulan22Kol" => $value->tahunBulan22Kol,
                "tahunBulan23Ht" => $value->tahunBulan23Ht,
                "tahunBulan23" => $value->tahunBulan23,
                "tahunBulan23Kol" => $value->tahunBulan23Kol,
                "tahunBulan24Ht" => $value->tahunBulan24Ht,
                "tahunBulan24" => $value->tahunBulan24,
                "tahunBulan24Kol" => $value->tahunBulan24Kol,
                "agunan" => [],
                "penjamin" => []
            ];

            M_SlikFasilitas::create($data_array);

            if (!empty($value->agunan) && is_array($value->agunan)){
                self::insert_slik_agunan($value->agunan,$slik_fasilitas_id);
            }

            if (!empty($value->penjamin) && is_array($value->penjamin)){
                self::insert_slik_penjamin($value->penjamin,$slik_fasilitas_id);
            }
        }
    }

    private function insert_slik_agunan($data_array,$last_id_fasilitas){

        foreach ($data_array as $list_agunan) {

            $data_agunan = [

                'ID' => Uuid::uuid4()->toString(),
                'SLIK_FASILITAS_ID' => $last_id_fasilitas,
                "jenisAgunanKet" => $list_agunan->jenisAgunanKet,
                "nilaiAgunanMenurutLJK" => $list_agunan->nilaiAgunanMenurutLJK,
                "prosentaseParipasu" => floatval($list_agunan->prosentaseParipasu),
                "tanggalUpdate" => $list_agunan->tanggalUpdate,
                "nomorAgunan" => $list_agunan->nomorAgunan,
                "jenisPengikatan" => $list_agunan->jenisPengikatan,
                "jenisPengikatanKet" => $list_agunan->jenisPengikatanKet,
                "tanggalPengikatan" => $list_agunan->tanggalPengikatan,
                "namaPemilikAgunan" => $list_agunan->namaPemilikAgunan,
                "alamatAgunan" => $list_agunan->alamatAgunan,
                "kabKotaLokasiAgunan" => $list_agunan->kabKotaLokasiAgunan,
                "kabKotaLokasiAgunanKet" => $list_agunan->kabKotaLokasiAgunanKet,
                "tglPenilaianPelapor" => $list_agunan->tglPenilaianPelapor,
                "peringkatAgunan" => $list_agunan->peringkatAgunan,
                "kodeLembagaPemeringkat" => $list_agunan->kodeLembagaPemeringkat,
                "lembagaPemeringkat" => $list_agunan->lembagaPemeringkat,
                "buktiKepemilikan" => $list_agunan->buktiKepemilikan,
                "nilaiAgunanNjop" => $list_agunan->nilaiAgunanNjop,
                "nilaiAgunanIndep" => floatval($list_agunan->nilaiAgunanIndep),
                "namaPenilaiIndep" => $list_agunan->namaPenilaiIndep,
                "asuransi" => $list_agunan->asuransi,
                "tanggalPenilaianPenilaiIndependen" =>$list_agunan->tanggalPenilaianPenilaiIndependen == ""? null : date('Y-m-d',strtotime($list_agunan->tanggalPenilaianPenilaiIndependen)),
                "keterangan" => $list_agunan->keterangan
            ];

            M_SlikAgunan::create($data_agunan);
        }
    }

    private function insert_slik_penjamin($data_array,$last_id_fasilitas){

        foreach ($data_array as $list_penjamin) {

            $data_penjamin = [
                'ID' => Uuid::uuid4()->toString(),
                'SLIK_FASILITAS_ID' => $last_id_fasilitas,
                "namaPenjamin" => $list_penjamin->namaPenjamin,
                "nomorIdentitas" => $list_penjamin->nomorIdentitas,
                "tanggalUpdate" => $list_penjamin->tanggalUpdate == ""? null : date('Y-m-d',strtotime( $list_penjamin->tanggalUpdate)),
                "tanggalBuat" => $list_penjamin->tanggalBuat == ""? null : date('Y-m-d',strtotime( $list_penjamin->tanggalBuat)),
                "kodeJenisPenjamin" => $list_penjamin->kodeJenisPenjamin,
                "keteranganJenisPenjamin" => $list_penjamin->keteranganJenisPenjamin,
                "alamatPenjamin" => $list_penjamin->alamatPenjamin,
                "keterangan" => $list_penjamin->keterangan
            ];

            M_SlikPenjamin::create($data_penjamin);
        }
    }

    private function slik_counter($req){
        $checkMax = M_SlikDebitur::max('SLIK_NUMBER');

        $currentDate = Carbon::now();
        $yearMonth = $currentDate->format('Ym');
        $lastYearMonth = substr($checkMax, 5, 6);
        $lastSequence = (int) substr($checkMax, 11, 5);

        $_trans = Carbon::now()->format('Ymd');
        
        if ($yearMonth != $lastYearMonth) {
            $newSequence = 1;
        } else {
            $newSequence = $lastSequence++;
        }        
        
        $generateCode = 'SLIK/'. $req->user()->id . '/'  . $_trans . '/' . sprintf("%05s", $newSequence);
        
        return $generateCode;
    }
}
