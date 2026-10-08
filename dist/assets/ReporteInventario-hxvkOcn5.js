import{T as he}from"./index-wH2rGfv9.js";import{G as I,p as xe,N as we,O as ke,H as $e,c as w,a as s,b as n,u as l,g as p,U as Ce,x as Se,l as Pe,z as Ee,i as A,F as Ne,t as u,f as Y,w as y,K as R,n as Ve,Z as De,L as Re,A as Q,bh as Ie,Q as Fe,j as c,M as L,o as x}from"./index-rSQBkoFA.js";import{s as Ue}from"./index-CEecQlvy.js";const ze=async()=>(await I.get("/branches")).data,Be=async(O,m=1,k=100,$="")=>(await I.get("/inventory/stock-by-branch",{params:{branch_id:O,page:m,per_page:k,search:$}})).data,W={list(){return I.get("/reports/products")},excel(){return I.get("/reports/products/excel",{responseType:"blob"})},pdf(){return I.get("/reports/products/pdf",{responseType:"blob"})}},Le={class:"inv"},Oe={class:"inv-header"},Te={class:"inv-actions"},je={class:"inv-card inv-filters"},Me={class:"field"},qe={class:"field field--grow"},Ae={class:"p-input-icon-left w-full"},He={class:"field field--buttons"},Ke={class:"inv-stats"},Ge={class:"stat"},Ye={class:"stat"},Qe={class:"stat"},We={class:"stat"},Xe={class:"inv-card inv-table"},Ze={class:"inv-table__head"},Je={class:"inv-branch"},ea={class:"inv-table__tools"},aa={class:"empty"},sa={class:"code"},ta={class:"product"},la={class:"product__name"},oa={key:0},na={key:0,class:"inv-pager"},ia={class:"inv-pager__info"},ra={class:"inv-pager__nav"},da={class:"inv-pager__page"},ca={key:1,class:"inv-card inv-welcome"},ua={class:"preview-bar"},pa={class:"preview-bar__count"},va={key:0,class:"empty"},ma=["srcdoc"],ga={__name:"ReporteInventario",setup(O){const m=we(),k=c(!1),$=c(!1),T=()=>{const a=new Date,e=o=>String(o).padStart(2,"0");return`${a.getFullYear()}${e(a.getMonth()+1)}${e(a.getDate())}_${e(a.getHours())}${e(a.getMinutes())}${e(a.getSeconds())}`},H=(a,e)=>{const o=window.URL.createObjectURL(a),t=document.createElement("a");t.href=o,t.download=e,document.body.appendChild(t),t.click(),t.remove(),window.URL.revokeObjectURL(o)},X=async()=>{k.value=!0;try{const a=await W.excel();H(a.data,`reporte_productos_${T()}.xlsx`),m.add({severity:"success",summary:"Excel generado",detail:"El reporte Excel fue descargado correctamente.",life:4e3})}catch(a){console.error("ERROR EXCEL:",a),m.add({severity:"error",summary:"Error",detail:"No se pudo generar el Excel.",life:4e3})}finally{k.value=!1}},Z=async()=>{$.value=!0;try{const a=await W.pdf();H(a.data,`reporte_productos_${T()}.pdf`),m.add({severity:"success",summary:"PDF generado",detail:"El reporte PDF fue descargado correctamente.",life:4e3})}catch(a){console.error("ERROR PDF:",a),m.add({severity:"error",summary:"Error",detail:"No se pudo generar el PDF.",life:4e3})}finally{$.value=!1}},K=c([]),g=c(null),j=c(!1),_=c([]),b=c(!1),r=c(1),J=c(100),E=c(0),v=c(1),f=c(""),h=c({}),N=c({}),ee=async()=>{var a;j.value=!0;try{const e=await ze();K.value=((a=e==null?void 0:e.data)==null?void 0:a.data)??[]}catch(e){console.error("Error cargando sucursales:",e),m.add({severity:"error",summary:"Error",detail:"No se pudieron cargar las sucursales.",life:4e3})}finally{j.value=!1}},C=async()=>{var e,o,t;if(!g.value){_.value=[],E.value=0;return}const a=Number(g.value.id);if(!a||Number.isNaN(a)){m.add({severity:"warn",summary:"Sucursal",detail:"La sucursal seleccionada no tiene un ID válido.",life:4e3});return}b.value=!0;try{const d=await Be(a,r.value,J.value,f.value),i=d==null?void 0:d.data;_.value=(i==null?void 0:i.data)??[],E.value=(i==null?void 0:i.total)??0,v.value=(i==null?void 0:i.last_page)??1}catch(d){console.error("ERROR PRODUCTOS:",d),console.error("RESPUESTA DEL SERVIDOR:",(e=d.response)==null?void 0:e.data),m.add({severity:"error",summary:"Error",detail:((t=(o=d.response)==null?void 0:o.data)==null?void 0:t.message)||"No se pudieron obtener los productos.",life:5e3})}finally{b.value=!1}};ke(g,async a=>{if(h.value={},N.value={},f.value="",r.value=1,!a){_.value=[],E.value=0,v.value=1;return}await C()});const M=async()=>{r.value=1,await C()},ae=async()=>{f.value="",await M()},se=async()=>{r.value<=1||(r.value--,await C())},te=async()=>{r.value>=v.value||(r.value++,await C())},le=async()=>{r.value!==1&&(r.value=1,await C())},oe=async()=>{r.value!==v.value&&(r.value=v.value,await C())},ne=a=>h.value[a]??null,ie=(a,e)=>{if(e==null){delete h.value[a.id],delete N.value[a.id];return}h.value[a.id]=Number(e),N.value[a.id]={id:a.id,codigo:a.product_code||"",producto:a.name||"",modelo:a.model_product||"",stock:Number(a.stock_actual??0)}},re=()=>{h.value={},N.value={}},F=a=>{const e=h.value[a.id];return e==null?null:Number(e)-Number(a.stock_actual??0)},de=a=>{const e=F(a);return e===null?"-":e>0?`+${e}`:`${e}`},ce=a=>{const e=F(a);return e===null||e===0?"secondary":e>0?"success":"danger"},ue=a=>{const e=F(a);return e===null||e===0?"":e>0?"row-sobrante":"row-faltante"},U=L(()=>{let a=0,e=0,o=0;for(const t of _.value){const d=F(t);d!==null&&(a++,d<0&&(e+=Math.abs(d)),d>0&&(o+=d))}return{contados:a,faltantes:e,sobrantes:o}}),q=L(()=>{var a;return((a=g.value)==null?void 0:a.branch_name)??"Sucursal"}),V=a=>new Intl.NumberFormat("es-BO").format(Number(a??0));$e(ee);const z=c(!1),G=c(null),pe=["Solo contados","Página actual"],D=c("Solo contados"),ve=()=>{D.value=Object.keys(h.value).length?"Solo contados":"Página actual",z.value=!0},B=L(()=>{const a=e=>{const o=h.value[e.id],t=o!=null;return{...e,contada:t?Number(o):null,diferencia:t?Number(o)-e.stock:null}};return D.value==="Solo contados"?Object.values(N.value).map(a).sort((e,o)=>e.producto.localeCompare(o.producto,"es")):_.value.map(e=>a({id:e.id,codigo:e.product_code||"",producto:e.name||"",modelo:e.model_product||"",stock:Number(e.stock_actual??0)}))}),S=a=>String(a??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#39;"),me=()=>{const a=new Date,e=o=>String(o).padStart(2,"0");return`${e(a.getDate())}/${e(a.getMonth()+1)}/${a.getFullYear()} ${e(a.getHours())}:${e(a.getMinutes())}`},ge=L(()=>{const a=B.value;let e=0,o=0,t=0;for(const i of a)i.diferencia!==null&&(e++,i.diferencia<0&&(o+=Math.abs(i.diferencia)),i.diferencia>0&&(t+=i.diferencia));const d=a.map((i,fe)=>{const P=i.diferencia,ye=P===null?"":P>0?`+${P}`:`${P}`,_e=P>0?"pos":P<0?"neg":"";return`
            <tr>
                <td class="n">${fe+1}</td>
                <td class="code">${S(i.codigo||"-")}</td>
                <td>
                    <div class="name">${S(i.producto)}</div>
                    ${i.modelo?`<div class="model">${S(i.modelo)}</div>`:""}
                </td>
                <td class="num">${i.stock}</td>
                <td class="num contada">${i.contada??""}</td>
                <td class="num ${_e}">${ye}</td>
            </tr>`}).join("");return`<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Inventario - ${S(q.value)}</title>
<style>
    @page { size: A4; margin: 12mm; }
    * { box-sizing: border-box; }
    body {
        font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #111; margin: 0;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    .page { padding: 0; }
    @media screen {
        body { background: #e5e7eb; padding: 16px; }
        .page { max-width: 780px; margin: 0 auto; background: #fff; padding: 28px; box-shadow: 0 2px 10px rgba(0,0,0,.15); }
    }
    h1 { font-size: 18px; margin: 0 0 4px; }
    .meta { color: #555; line-height: 1.6; margin-bottom: 10px; }
    .kpis { display: flex; gap: 18px; margin: 0 0 12px; padding: 8px 10px; background: #f1f5f9; border-radius: 4px; }
    .kpis b { font-size: 13px; }
    table { width: 100%; border-collapse: collapse; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    th { background: #334155; color: #fff; text-align: left; padding: 6px 8px; font-size: 10.5px; }
    td { padding: 5px 8px; border-bottom: 1px solid #ddd; vertical-align: top; }
    tbody tr:nth-child(even) td { background: #f6f7f9; }
    th.num, td.num { text-align: right; white-space: nowrap; }
    td.n { color: #888; width: 28px; }
    .code { font-family: Consolas, monospace; white-space: nowrap; }
    .name { font-weight: 600; }
    .model { color: #666; font-size: 10px; }
    .contada { font-weight: 700; }
    .pos { color: #16a34a; font-weight: 700; }
    .neg { color: #dc2626; font-weight: 700; }
    .firmas { display: flex; gap: 40px; margin-top: 48px; page-break-inside: avoid; }
    .firmas div { flex: 1; border-top: 1px solid #333; padding-top: 4px; text-align: center; color: #555; }
</style>
</head>
<body>
<div class="page">
    <h1>Inventario</h1>
    <div class="meta">
        Sucursal: <strong>${S(q.value)}</strong><br>
        Generado: ${me()}
        ${D.value==="Página actual"?` &nbsp;|&nbsp; Página ${r.value} de ${v.value}`:""}
        ${f.value?` &nbsp;|&nbsp; Búsqueda: ${S(f.value)}`:""}
    </div>
    <div class="kpis">
        <span>Productos: <b>${a.length}</b></span>
        <span>Contados: <b>${e}</b></span>
        <span>Faltantes: <b class="neg">${o}</b></span>
        <span>Sobrantes: <b class="pos">${t}</b></span>
    </div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Código</th>
                <th>Producto</th>
                <th class="num">Stock</th>
                <th class="num">Contada</th>
                <th class="num">Dif.</th>
            </tr>
        </thead>
        <tbody>${d}</tbody>
    </table>
    <div class="firmas">
        <div>Responsable</div>
        <div>Revisado por</div>
    </div>
</div>
</body>
</html>`}),be=()=>{var e;const a=(e=G.value)==null?void 0:e.contentWindow;a&&(a.focus(),a.print())};return(a,e)=>{const o=he;return x(),w("div",Le,[s("header",Oe,[e[5]||(e[5]=s("div",{class:"inv-title"},[s("span",{class:"inv-title__icon"},[s("i",{class:"pi pi-box"})]),s("div",null,[s("h2",null,"Inventario"),s("p",null,"Consulta y control de stock por sucursal.")])],-1)),s("div",Te,[n(l(p),{label:"Excel",icon:"pi pi-file-excel",severity:"success",outlined:"",loading:k.value,disabled:$.value,onClick:X},null,8,["loading","disabled"]),n(l(p),{label:"PDF",icon:"pi pi-file-pdf",severity:"danger",outlined:"",loading:$.value,disabled:k.value,onClick:Z},null,8,["loading","disabled"])])]),s("section",je,[s("div",Me,[e[6]||(e[6]=s("label",{for:"sucursal"},"Sucursal",-1)),n(l(Ue),{id:"sucursal",modelValue:g.value,"onUpdate:modelValue":e[0]||(e[0]=t=>g.value=t),options:K.value,optionLabel:"branch_name",placeholder:"Seleccione una sucursal",class:"w-full",loading:j.value},null,8,["modelValue","options","loading"])]),s("div",qe,[e[8]||(e[8]=s("label",{for:"buscar"},"Buscar producto",-1)),s("span",Ae,[e[7]||(e[7]=s("i",{class:"pi pi-search"},null,-1)),n(l(Se),{id:"buscar",modelValue:f.value,"onUpdate:modelValue":e[1]||(e[1]=t=>f.value=t),placeholder:"Código, producto o modelo",class:"w-full",disabled:!g.value,onKeyup:Ce(M,["enter"])},null,8,["modelValue","disabled"])])]),s("div",He,[n(l(p),{label:"Buscar",icon:"pi pi-search",loading:b.value,disabled:!g.value,onClick:M},null,8,["loading","disabled"]),f.value?Pe((x(),Ee(l(p),{key:0,icon:"pi pi-times",severity:"secondary",outlined:"","aria-label":"Limpiar búsqueda",disabled:b.value,onClick:ae},null,8,["disabled"])),[[o,"Limpiar búsqueda",void 0,{top:!0}]]):A("",!0)])]),g.value?(x(),w(Ne,{key:0},[s("section",Ke,[s("div",Ge,[e[10]||(e[10]=s("span",{class:"stat__icon stat__icon--primary"},[s("i",{class:"pi pi-list"})],-1)),s("div",null,[e[9]||(e[9]=s("small",null,"Productos",-1)),s("strong",null,u(V(E.value)),1)])]),s("div",Ye,[e[12]||(e[12]=s("span",{class:"stat__icon stat__icon--info"},[s("i",{class:"pi pi-check-square"})],-1)),s("div",null,[e[11]||(e[11]=s("small",null,"Contados (página)",-1)),s("strong",null,u(U.value.contados),1)])]),s("div",Qe,[e[14]||(e[14]=s("span",{class:"stat__icon stat__icon--danger"},[s("i",{class:"pi pi-arrow-down"})],-1)),s("div",null,[e[13]||(e[13]=s("small",null,"Faltantes",-1)),s("strong",null,u(V(U.value.faltantes)),1)])]),s("div",We,[e[16]||(e[16]=s("span",{class:"stat__icon stat__icon--success"},[s("i",{class:"pi pi-arrow-up"})],-1)),s("div",null,[e[15]||(e[15]=s("small",null,"Sobrantes",-1)),s("strong",null,u(V(U.value.sobrantes)),1)])])]),s("section",Xe,[s("div",Ze,[s("div",Je,[e[17]||(e[17]=s("i",{class:"pi pi-building"},null,-1)),s("span",null,u(q.value),1)]),s("div",ea,[n(l(p),{label:"Vista previa",icon:"pi pi-print",outlined:"",size:"small",onClick:ve}),n(l(p),{label:"Limpiar cantidades",icon:"pi pi-eraser",text:"",size:"small",disabled:!U.value.contados,onClick:re},null,8,["disabled"]),n(l(Y),{value:`Página ${r.value} de ${v.value}`,severity:"info"},null,8,["value"])])]),n(l(Re),{value:_.value,loading:b.value,dataKey:"id",responsiveLayout:"scroll",rowClass:ue,size:"small"},{empty:y(()=>[s("div",aa,[e[18]||(e[18]=s("i",{class:"pi pi-inbox"},null,-1)),e[19]||(e[19]=s("strong",null,"Sin resultados",-1)),s("span",null,u(f.value?"No hay productos que coincidan con la búsqueda.":"Esta sucursal no tiene productos."),1)])]),default:y(()=>[n(l(R),{header:"Código",style:{"min-width":"130px"}},{body:y(({data:t})=>[s("span",sa,u(t.product_code||"-"),1)]),_:1}),n(l(R),{header:"Producto",style:{"min-width":"280px"}},{body:y(({data:t})=>[s("div",ta,[s("span",la,u(t.name),1),t.model_product?(x(),w("small",oa,u(t.model_product),1)):A("",!0)])]),_:1}),n(l(R),{header:"Stock",style:{width:"130px"},bodyClass:"text-right",headerClass:"text-right"},{body:y(({data:t})=>[s("span",{class:Ve(["stock",{"stock--zero":Number(t.stock_actual??0)<=0}])},u(V(t.stock_actual)),3)]),_:1}),n(l(R),{header:"Cantidad contada",style:{width:"190px"}},{body:y(({data:t})=>[n(l(De),{modelValue:ne(t.id),"onUpdate:modelValue":d=>ie(t,d),min:0,useGrouping:!1,placeholder:"0",class:"w-full",inputClass:"w-full text-center"},null,8,["modelValue","onUpdate:modelValue"])]),_:1}),n(l(R),{header:"Diferencia",style:{width:"130px"},bodyClass:"text-center",headerClass:"text-center"},{body:y(({data:t})=>[n(l(Y),{value:de(t),severity:ce(t),rounded:""},null,8,["value","severity"])]),_:1})]),_:1},8,["value","loading"]),_.value.length?(x(),w("div",na,[s("span",ia,[e[20]||(e[20]=Q(" Mostrando ",-1)),s("strong",null,u(_.value.length),1),e[21]||(e[21]=Q(" de ",-1)),s("strong",null,u(V(E.value)),1)]),s("div",ra,[n(l(p),{icon:"pi pi-angle-double-left",text:"",rounded:"","aria-label":"Primera página",disabled:r.value<=1||b.value,onClick:le},null,8,["disabled"]),n(l(p),{icon:"pi pi-angle-left",text:"",rounded:"","aria-label":"Página anterior",disabled:r.value<=1||b.value,onClick:se},null,8,["disabled"]),s("span",da,u(r.value)+" / "+u(v.value),1),n(l(p),{icon:"pi pi-angle-right",text:"",rounded:"","aria-label":"Página siguiente",disabled:r.value>=v.value||b.value,onClick:te},null,8,["disabled"]),n(l(p),{icon:"pi pi-angle-double-right",text:"",rounded:"","aria-label":"Última página",disabled:r.value>=v.value||b.value,onClick:oe},null,8,["disabled"])])])):A("",!0)])],64)):(x(),w("section",ca,[...e[22]||(e[22]=[s("span",{class:"inv-welcome__icon"},[s("i",{class:"pi pi-building"})],-1),s("h3",null,"Seleccione una sucursal",-1),s("p",null,"Elija una sucursal arriba para ver su inventario y comparar el stock con lo contado.",-1)])])),n(l(Fe),{visible:z.value,"onUpdate:visible":e[4]||(e[4]=t=>z.value=t),modal:"",header:"Vista previa de impresión",draggable:!1,style:{width:"60rem"},breakpoints:{"1199px":"90vw","640px":"98vw"}},{footer:y(()=>[n(l(p),{label:"Cerrar",severity:"secondary",text:"",onClick:e[3]||(e[3]=t=>z.value=!1)}),n(l(p),{label:"Imprimir",icon:"pi pi-print",disabled:!B.value.length,onClick:be},null,8,["disabled"])]),default:y(()=>[s("div",ua,[n(l(Ie),{modelValue:D.value,options:pe,"onUpdate:modelValue":e[2]||(e[2]=t=>t&&(D.value=t))},null,8,["modelValue"]),s("small",pa,u(B.value.length)+" productos ",1)]),B.value.length?(x(),w("iframe",{key:1,ref_key:"previewFrame",ref:G,class:"preview-frame",title:"Vista previa de impresión",srcdoc:ge.value},null,8,ma)):(x(),w("div",va,[...e[23]||(e[23]=[s("i",{class:"pi pi-inbox"},null,-1),s("strong",null,"Nada para imprimir",-1),s("span",null,' Ingrese cantidades en la tabla o cambie a "Página actual". ',-1)])]))]),_:1},8,["visible"])])}}},_a=xe(ga,[["__scopeId","data-v-4fdfc664"]]);export{_a as default};
