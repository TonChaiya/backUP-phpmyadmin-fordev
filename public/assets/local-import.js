(() => {
    'use strict';
    const root=document.querySelector('[data-local-import]'); if(!root)return;
    const prepare=root.querySelector('[data-import-prepare]'), execute=root.querySelector('[data-import-execute]');
    const message=root.querySelector('[data-import-message]');let id=root.dataset.activeJob||'', timer;
    const endpoint=root.dataset.api, csrf=root.dataset.csrf;
    const fallback='ไม่สามารถรับผลจากระบบได้ กรุณาตรวจสถานะงานหรือให้ผู้ดูแลตรวจสอบก่อน โดยไม่กด Import ซ้ำ';
    const stateLabels={awaiting_provisioning:'รอบัญชีและฐานใหม่',running:'กำลังทำงาน',succeeded:'การทำงานตามขั้นตอนเสร็จแล้ว',failed:'งานเดิมไม่ผ่าน',interrupted:'งานถูกขัดจังหวะ'};
    const labels={execution:{completed:'Import เสร็จสิ้น',running:'กำลัง Import',not_started:'ยังไม่ได้เริ่ม',failed:'ไม่สำเร็จหรือยังยืนยันการทำงานไม่ได้'},
        schema:{verified:'ผ่านการตรวจ Schema',not_verified:'ยังไม่ยืนยัน Schema'},
        live_data:{verified:'ค่าข้อมูลตรงกับ Live Source ณ เวลาตรวจ',not_verified:'ยังไม่ยืนยันค่าทุกค่ากับ Live Source'},
        snapshot_data:{not_verified:'NOT VERIFIED — ยังไม่ยืนยันค่าจาก SQL Snapshot'},
        verification:{pending:'รอผลตรวจ',failed:'ผลตรวจยังไม่ผ่าน',partial:'PARTIAL — ยังไม่ใช่ Fully verified'}};
    function failure(payload){const error=new Error();error.safeMessage=typeof payload?.message==='string'?payload.message:fallback;return error;}
    function showError(error){message.textContent=error?.safeMessage||fallback;}
    function render(job){
        clearTimeout(timer);id=job.id;root.querySelector('[data-import-state]').textContent=stateLabels[job.status]||'ยังไม่ทราบสถานะ';
        const details=root.querySelector('[data-import-details]');details.replaceChildren();
        for(const [label,value] of [['Job',job.id],['Source',job.artifact.database],['SQL',job.artifact.relative_path],['Bytes',job.artifact.bytes],['SHA-256',job.artifact.sha256],['New target',job.target],['Import account',job.username+'@localhost']]){
            const row=document.createElement('div'),term=document.createElement('dt'),desc=document.createElement('dd');term.textContent=label;desc.textContent=String(value);row.append(term,desc);details.append(row);
        }
        execute.hidden=job.status!=='awaiting_provisioning'||!job.provisioning_ready;execute.elements.job_id.value=id;
        root.querySelector('[data-import-readiness]').textContent=job.status==='awaiting_provisioning'
            ? (job.provisioning_ready ? (job.source_reader_connected?'บัญชีและ ownership พร้อม กรุณายืนยัน Import':'กรุณาเชื่อม backup_reader ที่หน้า Export ก่อน แล้วกลับมายืนยัน Import'):'ต้องอนุมัติและ provision บัญชี/ฐานตามชื่อที่แสดงก่อน ขณะนี้ยังเริ่ม SQL ไม่ได้')
            : 'งานที่เริ่มแล้วจะไม่ลองใหม่อัตโนมัติ ฐานปลายทางถูกเก็บไว้เพื่อตรวจสอบ';
        const p=job.presentation||{};
        message.textContent=p.message||'';
        for(const [key,selector] of [['execution','execution'],['schema','schema'],['live_data','live-data'],['snapshot_data','snapshot-data'],['verification','verification']]){
            const value=key==='snapshot_data'?'not_verified':p[key];
            root.querySelector(`[data-import-${selector}]`).textContent=labels[key][value]||'ยังไม่มีผลตรวจ';
        }
        const date=p.reviewed_at?new Date(p.reviewed_at):null;
        root.querySelector('[data-import-review-note]').textContent=(date&&!Number.isNaN(date.getTime())
            ?`ผลตรวจเพิ่มเติม ณ ${date.toLocaleString('th-TH')} · `:'')
            +`สถานะงานที่บันทึก: ${stateLabels[job.status]||'ไม่ทราบ'} (${Object.hasOwn(stateLabels,job.status)?job.status:'unknown'}) · ไม่เปลี่ยนประวัติเดิม และไม่ได้ยืนยัน SQL Snapshot`;
        if(job.status==='running'){clearTimeout(timer);timer=setTimeout(status,1500);}
    }
    async function request(body){
        const response=await fetch(endpoint,{method:'POST',body,credentials:'same-origin',cache:'no-store'});
        const payload=await response.json();if(!response.ok||!payload.ok)throw failure(payload);return payload.job;
    }
    async function status(){
        if(!id)return;
        try{const response=await fetch(`${endpoint}?action=status&job_id=${encodeURIComponent(id)}`,{credentials:'same-origin',cache:'no-store',headers:{'X-CSRF-Token':csrf}});const p=await response.json();if(!response.ok||!p.ok)throw failure(p);render(p.job);}
        catch(e){showError(e);}
    }
    prepare.addEventListener('submit',async e=>{
        e.preventDefault();const button=prepare.querySelector('button');button.disabled=true;message.textContent='กำลังตรวจไฟล์จริง…';
        try{render(await request(new FormData(prepare)));}catch(error){showError(error);}finally{button.disabled=false;}
    });
    execute.addEventListener('submit',async e=>{
        e.preventDefault();const button=execute.querySelector('button');button.disabled=true;message.textContent='กำลังตรวจสิทธิ์และ Import…';
        const body=new FormData(execute);execute.elements.import_password.value='';
        const running=request(body);timer=setTimeout(status,1500);
        let requestError=null;
        try{render(await running);}catch(error){requestError=error;showError(error);}finally{
            button.disabled=false;clearTimeout(timer);await status();
            if(requestError)showError(requestError);
        }
    });
    if(id)void status();
})();
