<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/config.conn.php';
require_once __DIR__ . '/../../helper/auth.helper.php';
$admin = require_api_login(['admin']);
require_csrf();
$action = $_GET['action'] ?? ($_POST['action'] ?? 'summary');

function json_out($data, $code = 200) { http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function esc($conn, $value) { return mysqli_real_escape_string($conn, trim((string)$value)); }

try {
    if ($action === 'summary') {
        $tables = ['cbt_peserta' => 'peserta', 'cbt_ruang' => 'ruang', 'cbt_audit_log' => 'audit', 'cbt_pengumuman' => 'pengumuman'];
        $data = [];
        foreach ($tables as $table => $key) {
            $q = mysqli_query($conn, "SELECT COUNT(*) cnt FROM $table");
            $data[$key] = $q ? (int)mysqli_fetch_assoc($q)['cnt'] : 0;
        }
        $data['php'] = PHP_VERSION;
        $data['database'] = mysqli_get_server_info($conn);
        $data['upload_writable'] = is_writable(__DIR__ . '/../../../uploads/cbt');
        json_out(['status' => 'success', 'data' => $data]);
    }

    if ($action === 'list_peserta') {
        $search = esc($conn, $_GET['search'] ?? '');
        $where = $search === '' ? '' : "WHERE p.no_peserta LIKE '%$search%' OR p.nama_lengkap LIKE '%$search%'";
        $q = mysqli_query($conn, "SELECT p.id_peserta,p.no_peserta,p.nama_lengkap,p.email,p.kelompok,p.status,p.created_at,
            r.nama_ruang,r.id_ruang FROM cbt_peserta p LEFT JOIN cbt_ruang r ON r.id_ruang=p.id_ruang $where ORDER BY p.id_peserta DESC LIMIT 1000");
        $rows=[]; while ($q && $row=mysqli_fetch_assoc($q)) { $rows[]=$row; }
        json_out(['status'=>'success','data'=>$rows]);
    }

    if ($action === 'save_peserta') {
        $id=(int)($_POST['id_peserta']??0); $no=esc($conn,$_POST['no_peserta']??'');
        $nama=esc($conn,$_POST['nama_lengkap']??''); $email=esc($conn,$_POST['email']??'');
        $kelompok=esc($conn,$_POST['kelompok']??''); $ruang=(int)($_POST['id_ruang']??0);
        $status=!empty($_POST['status'])?1:0; $password=trim((string)($_POST['password']??''));
        if ($no==='' || $nama==='') json_out(['status'=>'error','msg'=>'Nomor dan nama peserta wajib diisi.'],422);
        if ($id===0 && strlen($password)<6) json_out(['status'=>'error','msg'=>'Peserta baru wajib memiliki PIN/password minimal 6 karakter.'],422);
        $dup=mysqli_query($conn,"SELECT id_peserta FROM cbt_peserta WHERE no_peserta='$no' AND id_peserta<>$id LIMIT 1");
        if ($dup && mysqli_num_rows($dup)) json_out(['status'=>'error','msg'=>'Nomor peserta sudah digunakan.'],409);
        $roomVal=$ruang>0?$ruang:'NULL'; $passSql='';
        if ($password!=='') { if(strlen($password)<6) json_out(['status'=>'error','msg'=>'PIN/password minimal 6 karakter.'],422); $hash=esc($conn,password_hash($password,PASSWORD_DEFAULT)); $passSql=",password='$hash'"; }
        if ($id>0) $ok=mysqli_query($conn,"UPDATE cbt_peserta SET no_peserta='$no',nama_lengkap='$nama',email='$email',kelompok='$kelompok',id_ruang=$roomVal,status=$status $passSql WHERE id_peserta=$id");
        else { $hash=$password!==''?"'".esc($conn,password_hash($password,PASSWORD_DEFAULT))."'":'NULL'; $ok=mysqli_query($conn,"INSERT INTO cbt_peserta(no_peserta,nama_lengkap,email,kelompok,id_ruang,status,password) VALUES('$no','$nama','$email','$kelompok',$roomVal,$status,$hash)"); $id=mysqli_insert_id($conn); }
        if(!$ok) throw new RuntimeException(mysqli_error($conn));
        audit_log($conn,'simpan_peserta','peserta',$id,['no_peserta'=>$no,'nama'=>$nama]);
        json_out(['status'=>'success','msg'=>'Data peserta tersimpan.','id'=>$id]);
    }

    if ($action === 'delete_peserta') {
        $id=(int)($_POST['id_peserta']??0); if($id<=0) json_out(['status'=>'error','msg'=>'ID tidak valid.'],422);
        $used=mysqli_query($conn,"SELECT 1 FROM cbt_sesi s JOIN cbt_peserta p ON p.no_peserta=s.no_pendaftaran WHERE p.id_peserta=$id LIMIT 1");
        if($used && mysqli_num_rows($used)) json_out(['status'=>'error','msg'=>'Peserta sudah memiliki riwayat ujian dan tidak boleh dihapus; nonaktifkan saja.'],409);
        mysqli_query($conn,"DELETE FROM cbt_peserta WHERE id_peserta=$id"); audit_log($conn,'hapus_peserta','peserta',$id);
        json_out(['status'=>'success','msg'=>'Peserta dihapus.']);
    }

    if ($action === 'assign_peserta') {
        $participant=(int)($_POST['id_peserta']??0); $schedule=(int)($_POST['id_jadwal']??0);
        if($participant<=0||$schedule<=0) json_out(['status'=>'error','msg'=>'Peserta dan jadwal wajib dipilih.'],422);
        $ok=mysqli_query($conn,"INSERT INTO cbt_peserta_jadwal(id_peserta,id_jadwal,status) VALUES($participant,$schedule,'diizinkan') ON DUPLICATE KEY UPDATE status='diizinkan'");
        if(!$ok) throw new RuntimeException(mysqli_error($conn));
        audit_log($conn,'tugaskan_peserta','jadwal',$schedule,['id_peserta'=>$participant]);
        json_out(['status'=>'success','msg'=>'Peserta ditugaskan ke jadwal.']);
    }

    if ($action === 'import_peserta') {
        $file=$_FILES['peserta_file']??null;
        if(!$file||($file['error']??1)!==UPLOAD_ERR_OK||($file['size']??0)>2*1024*1024||strtolower(pathinfo($file['name'],PATHINFO_EXTENSION))!=='csv') json_out(['status'=>'error','msg'=>'Pilih CSV peserta maksimal 2 MB.'],422);
        $handle=fopen($file['tmp_name'],'r'); $header=fgetcsv($handle); $header=array_map(fn($v)=>strtolower(trim((string)$v)),$header?:[]);
        if(!in_array('no_peserta',$header,true)||!in_array('nama_lengkap',$header,true)) json_out(['status'=>'error','msg'=>'CSV wajib memiliki kolom no_peserta dan nama_lengkap.'],422);
        $count=0;$skipped=0;mysqli_begin_transaction($conn);
        while(($values=fgetcsv($handle))!==false){$row=array_combine($header,array_pad($values,count($header),''));if(!$row){$skipped++;continue;}$no=esc($conn,$row['no_peserta']??'');$nama=esc($conn,$row['nama_lengkap']??'');$password=trim($row['password']??'');if($no===''||$nama===''||strlen($password)<6){$skipped++;continue;}$email=esc($conn,$row['email']??'');$group=esc($conn,$row['kelompok']??'');$hash="'".esc($conn,password_hash($password,PASSWORD_DEFAULT))."'";$ok=mysqli_query($conn,"INSERT INTO cbt_peserta(no_peserta,nama_lengkap,email,kelompok,password,status) VALUES('$no','$nama','$email','$group',$hash,1) ON DUPLICATE KEY UPDATE nama_lengkap=VALUES(nama_lengkap),email=VALUES(email),kelompok=VALUES(kelompok),password=VALUES(password),status=1");if($ok)$count++;else$skipped++;}
        fclose($handle);mysqli_commit($conn);audit_log($conn,'import_peserta','peserta',null,['diproses'=>$count,'dilewati'=>$skipped]);json_out(['status'=>'success','msg'=>"$count peserta diproses; $skipped dilewati."]);
    }

    if ($action === 'list_ruang') {
        $q=mysqli_query($conn,"SELECT * FROM cbt_ruang ORDER BY nama_ruang"); $rows=[]; while($q&&$r=mysqli_fetch_assoc($q))$rows[]=$r;
        json_out(['status'=>'success','data'=>$rows]);
    }
    if ($action === 'save_ruang') {
        $id=(int)($_POST['id_ruang']??0); $kode=strtoupper(esc($conn,$_POST['kode_ruang']??'')); $nama=esc($conn,$_POST['nama_ruang']??'');
        $lokasi=esc($conn,$_POST['lokasi']??''); $kap=max(0,(int)($_POST['kapasitas']??0));
        if($kode===''||$nama==='') json_out(['status'=>'error','msg'=>'Kode dan nama ruang wajib diisi.'],422);
        $ok=$id?mysqli_query($conn,"UPDATE cbt_ruang SET kode_ruang='$kode',nama_ruang='$nama',lokasi='$lokasi',kapasitas=$kap WHERE id_ruang=$id"):
            mysqli_query($conn,"INSERT INTO cbt_ruang(kode_ruang,nama_ruang,lokasi,kapasitas) VALUES('$kode','$nama','$lokasi',$kap)");
        if(!$ok) json_out(['status'=>'error','msg'=>'Kode ruang sudah digunakan atau data tidak valid.'],409);
        audit_log($conn,'simpan_ruang','ruang',$id?:mysqli_insert_id($conn),['kode'=>$kode]); json_out(['status'=>'success','msg'=>'Ruang tersimpan.']);
    }

    if ($action === 'list_audit') {
        $page=max(1,(int)($_GET['page']??1));
        $perPage=(int)($_GET['per_page']??10); $perPage=in_array($perPage,[10,25,50,100],true)?$perPage:10;
        $search=esc($conn,$_GET['search']??'');
        $where=$search===''?'':"WHERE username LIKE '%$search%' OR role LIKE '%$search%' OR aksi LIKE '%$search%' OR entitas LIKE '%$search%' OR id_entitas LIKE '%$search%' OR ip_address LIKE '%$search%'";
        $countQ=mysqli_query($conn,"SELECT COUNT(*) total FROM cbt_audit_log $where");
        $total=$countQ?(int)mysqli_fetch_assoc($countQ)['total']:0; $pages=max(1,(int)ceil($total/$perPage));
        if($page>$pages)$page=$pages; $offset=($page-1)*$perPage;
        $q=mysqli_query($conn,"SELECT * FROM cbt_audit_log $where ORDER BY id_log DESC LIMIT $perPage OFFSET $offset"); $rows=[];
        while($q&&$r=mysqli_fetch_assoc($q)){$r['detail']=json_decode($r['detail_json']??'{}',true);unset($r['detail_json']);$rows[]=$r;}
        json_out(['status'=>'success','data'=>$rows,'meta'=>['page'=>$page,'per_page'=>$perPage,'total'=>$total,'pages'=>$pages,'from'=>$total?($offset+1):0,'to'=>min($offset+$perPage,$total)]]);
    }

    if ($action === 'list_pengumuman') {
        $q=mysqli_query($conn,"SELECT * FROM cbt_pengumuman ORDER BY id_pengumuman DESC");$rows=[];while($q&&$r=mysqli_fetch_assoc($q))$rows[]=$r;
        json_out(['status'=>'success','data'=>$rows]);
    }

    if ($action === 'restore_backup') {
        if (($_POST['confirmation'] ?? '') !== 'PULIHKAN') json_out(['status'=>'error','msg'=>'Ketik PULIHKAN untuk mengonfirmasi.'],422);
        $file=$_FILES['backup_file']??null;
        if(!$file||($file['error']??1)!==UPLOAD_ERR_OK||($file['size']??0)>25*1024*1024||strtolower(pathinfo($file['name'],PATHINFO_EXTENSION))!=='sql')
            json_out(['status'=>'error','msg'=>'Berkas backup SQL tidak valid atau melebihi 25 MB.'],422);
        $sql=file_get_contents($file['tmp_name']);
        if(stripos($sql,'CREATE TABLE')===false||stripos($sql,'SET FOREIGN_KEY_CHECKS')===false)
            json_out(['status'=>'error','msg'=>'Format backup tidak dikenali.'],422);
        if(!mysqli_multi_query($conn,$sql)) throw new RuntimeException(mysqli_error($conn));
        do { if($result=mysqli_store_result($conn)) mysqli_free_result($result); } while(mysqli_more_results($conn)&&mysqli_next_result($conn));
        audit_log($conn,'pulihkan_backup','database',DB_NAME,['file'=>basename($file['name'])]);
        json_out(['status'=>'success','msg'=>'Database berhasil dipulihkan. Silakan masuk kembali.']);
    }
    if ($action === 'save_pengumuman') {
        $judul=esc($conn,$_POST['judul']??'');$isi=esc($conn,$_POST['isi']??'');$target=esc($conn,$_POST['target']??'semua');
        if($judul===''||$isi==='')json_out(['status'=>'error','msg'=>'Judul dan isi wajib diisi.'],422);
        $existing=mysqli_query($conn,"SELECT id_pengumuman FROM cbt_pengumuman WHERE judul='$judul' AND isi='$isi' AND target='$target' AND status=1 LIMIT 1");
        if($existing&&($same=mysqli_fetch_assoc($existing))) json_out(['status'=>'success','msg'=>'Pengumuman identik sudah pernah diterbitkan.','id'=>(int)$same['id_pengumuman'],'duplicate'=>true]);
        $uid=(int)$admin['id'];mysqli_query($conn,"INSERT INTO cbt_pengumuman(judul,isi,target,id_admin) VALUES('$judul','$isi','$target',$uid)");
        $id=mysqli_insert_id($conn);audit_log($conn,'buat_pengumuman','pengumuman',$id,['judul'=>$judul]);json_out(['status'=>'success','msg'=>'Pengumuman diterbitkan.']);
    }

    if ($action === 'delete_pengumuman') {
        $id=(int)($_POST['id_pengumuman']??0); if($id<=0)json_out(['status'=>'error','msg'=>'ID pengumuman tidak valid.'],422);
        mysqli_query($conn,"DELETE FROM cbt_pengumuman WHERE id_pengumuman=$id");
        audit_log($conn,'hapus_pengumuman','pengumuman',$id);json_out(['status'=>'success','msg'=>'Pengumuman berhasil dihapus.']);
    }

    json_out(['status'=>'error','msg'=>'Aksi tidak dikenal.'],404);
} catch (Throwable $e) {
    error_log('Operations API: '.$e->getMessage());
    json_out(['status'=>'error','msg'=>'Terjadi kesalahan internal.'],500);
}
