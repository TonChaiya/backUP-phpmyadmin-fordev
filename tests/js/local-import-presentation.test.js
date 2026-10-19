'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const code=fs.readFileSync(require('node:path').join(__dirname,'../../public/assets/local-import.js'),'utf8');
class Element {
    constructor(){this.textContent='';this.hidden=false;this.elements={job_id:{value:''},import_password:{value:''}};this.handlers={};}
    replaceChildren(){this.children=[];}
    append(...children){this.children=(this.children||[]).concat(children);}
    addEventListener(name,handler){this.handlers[name]=handler;}
    querySelector(){return this.button||(this.button={disabled:false});}
}
async function run(payload,networkFailure=false,postPayload=null){
    const nodes=new Map(),requests=[];
    const root={dataset:{activeJob:'fixture-job',api:'import-job.php',csrf:'fixture-csrf'},querySelector(selector){if(!nodes.has(selector))nodes.set(selector,new Element());return nodes.get(selector);}};
    const context={document:{querySelector:()=>root,createElement:()=>new Element()},
        setTimeout:()=>1,clearTimeout:()=>{},FormData:class{},fetch:async(url,options)=>{
            requests.push({url,method:options.method||'GET'});if(networkFailure)throw new Error('synthetic-secret internal network trace');
            const result=options.method==='POST'&&postPayload?postPayload:payload;
            return {ok:result.ok,json:async()=>result};
        }};
    vm.runInNewContext(code,context);await new Promise(resolve=>setImmediate(resolve));
    assert.deepEqual(requests.map(r=>r.method),['GET'],'render must never start Import/prepare automatically');
    return {nodes,root,requests};
}
const job={id:'fixture-job',status:'failed',target:'generated-target',username:'generated-account',error:'synthetic-secret internal exception',
    artifact:{database:'autoprintdb',relative_path:'autoprintdb/fixture.sql',bytes:10,sha256:'a'.repeat(64)},events:[{code:'synthetic-secret internal event'}],
    provisioning_ready:false,source_reader_connected:false,presentation:{execution:'completed',schema:'verified',live_data:'verified',snapshot_data:'verified',
        verification:'partial',reviewed_at:'2026-10-19T02:51:02Z',message:'ผลตรวจเพิ่มเติมไม่เปลี่ยนประวัติงานเดิม'}};
(async()=>{
    let ui=await run({ok:true,job});
    assert.equal(ui.nodes.get('[data-import-execution]').textContent,'Import เสร็จสิ้น');
    assert.equal(ui.nodes.get('[data-import-schema]').textContent,'ผ่านการตรวจ Schema');
    assert.match(ui.nodes.get('[data-import-live-data]').textContent,/Live Source/);
    assert.match(ui.nodes.get('[data-import-snapshot-data]').textContent,/^NOT VERIFIED/);
    assert.equal(ui.nodes.get('[data-import-verification]').textContent,'PARTIAL — ยังไม่ใช่ Fully verified');
    assert.match(ui.nodes.get('[data-import-review-note]').textContent,/failed/);
    assert.equal(ui.nodes.get('[data-import-execute]').hidden,true);
    assert(!Array.from(ui.nodes.values()).some(e=>e.textContent.includes('synthetic-secret')));
    console.log('PASS completed execution and reviewed failure stay distinct; snapshot cannot be upgraded; no raw details');
    ui=await run({ok:true,job:{...job,status:'awaiting_provisioning',presentation:{execution:'not_started',schema:'not_verified',live_data:'not_verified',verification:'pending'}}});
    assert.equal(ui.nodes.get('[data-import-execution]').textContent,'ยังไม่ได้เริ่ม');assert.equal(ui.nodes.get('[data-import-verification]').textContent,'รอผลตรวจ');
    assert.equal(ui.nodes.get('[data-import-execute]').hidden,true);
    console.log('PASS pending unprovisioned job cannot expose execution controls');
    ui=await run({ok:false,error:'synthetic-secret internal SQL'});
    assert(!ui.nodes.get('[data-import-message]').textContent.includes('synthetic-secret'));
    console.log('PASS API error without curated message uses safe actionable fallback');
    ui=await run(null,true);
    assert(!ui.nodes.get('[data-import-message]').textContent.includes('synthetic-secret'));
    console.log('PASS network exception never prints internal exception details');
    ui=await run({ok:true,job:{...job,status:'awaiting_provisioning',provisioning_ready:true,presentation:{execution:'not_started',verification:'pending',message:''}}},false,{ok:false,message:'กรุณาตรวจรหัสผ่านบัญชีเฉพาะงาน'});
    await ui.nodes.get('[data-import-execute]').handlers.submit({preventDefault(){}});
    assert.equal(ui.nodes.get('[data-import-message]').textContent,'กรุณาตรวจรหัสผ่านบัญชีเฉพาะงาน');
    assert.deepEqual(ui.requests.map(r=>r.method),['GET','POST','GET']);
    console.log('PASS rejected execution guidance survives status reconciliation; no password form is submitted');
    ui=await run({ok:true,job:{...job,status:'awaiting_provisioning',provisioning_ready:true,connection_context_valid:false}});
    assert.equal(ui.nodes.get('[data-import-execute]').hidden,true);
    console.log('PASS changed saved reader context hides execution even when provisioning is ready');
    console.log('Tests: 6, Passed: 6, Failed: 0');
})().catch(()=>{console.error('FAIL local Import presentation behavior');process.exitCode=1;});
