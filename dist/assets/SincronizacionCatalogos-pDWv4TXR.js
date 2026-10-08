import{T as Tt}from"./index-wH2rGfv9.js";import{B as q,C as Q,o as v,c as g,a2 as V,E as x,ax as ft,aS as $t,aT as Bt,a7 as ht,aU as et,bi as St,aC as Z,aZ as Ct,aV as M,aJ as Nt,bj as ct,a0 as mt,l as E,z as _,a3 as it,i as N,a as s,aE as Ot,aa as At,aW as W,w as f,n as zt,N as It,O as Pt,H as Lt,b as c,u as l,I as ut,g as at,A as S,t as w,F as L,r as st,f as nt,L as bt,K as C,T as Vt,R as Et,x as Kt,Q as Rt,j as m,M as B,bk as Ft}from"./index-rSQBkoFA.js";import{s as Dt}from"./index-DEfECpRu.js";import{s as vt}from"./index-D9GSlDp0.js";import{s as pt}from"./index-Dtkjev3H.js";import{s as jt}from"./index-D3VNVlBP.js";import{s as H}from"./sincronizacionServices-BbJs-sdM.js";import{b as Ut}from"./branchServices-C-amZgWg.js";var Mt=`
    .p-tabs {
        display: flex;
        flex-direction: column;
    }

    .p-tablist {
        display: flex;
        position: relative;
        overflow: hidden;
        background: dt('tabs.tablist.background');
    }

    .p-tablist-viewport {
        overflow-x: auto;
        overflow-y: hidden;
        scroll-behavior: smooth;
        scrollbar-width: none;
        overscroll-behavior: contain auto;
    }

    .p-tablist-viewport::-webkit-scrollbar {
        display: none;
    }

    .p-tablist-tab-list {
        position: relative;
        display: flex;
        border-style: solid;
        border-color: dt('tabs.tablist.border.color');
        border-width: dt('tabs.tablist.border.width');
    }

    .p-tablist-content {
        flex-grow: 1;
    }

    .p-tablist-nav-button {
        all: unset;
        position: absolute !important;
        flex-shrink: 0;
        inset-block-start: 0;
        z-index: 2;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: dt('tabs.nav.button.background');
        color: dt('tabs.nav.button.color');
        width: dt('tabs.nav.button.width');
        transition:
            color dt('tabs.transition.duration'),
            outline-color dt('tabs.transition.duration'),
            box-shadow dt('tabs.transition.duration');
        box-shadow: dt('tabs.nav.button.shadow');
        outline-color: transparent;
        cursor: pointer;
    }

    .p-tablist-nav-button:focus-visible {
        z-index: 1;
        box-shadow: dt('tabs.nav.button.focus.ring.shadow');
        outline: dt('tabs.nav.button.focus.ring.width') dt('tabs.nav.button.focus.ring.style') dt('tabs.nav.button.focus.ring.color');
        outline-offset: dt('tabs.nav.button.focus.ring.offset');
    }

    .p-tablist-nav-button:hover {
        color: dt('tabs.nav.button.hover.color');
    }

    .p-tablist-prev-button {
        inset-inline-start: 0;
    }

    .p-tablist-next-button {
        inset-inline-end: 0;
    }

    .p-tablist-prev-button:dir(rtl),
    .p-tablist-next-button:dir(rtl) {
        transform: rotate(180deg);
    }

    .p-tab {
        flex-shrink: 0;
        cursor: pointer;
        user-select: none;
        position: relative;
        border-style: solid;
        white-space: nowrap;
        gap: dt('tabs.tab.gap');
        background: dt('tabs.tab.background');
        border-width: dt('tabs.tab.border.width');
        border-color: dt('tabs.tab.border.color');
        color: dt('tabs.tab.color');
        padding: dt('tabs.tab.padding');
        font-weight: dt('tabs.tab.font.weight');
        transition:
            background dt('tabs.transition.duration'),
            border-color dt('tabs.transition.duration'),
            color dt('tabs.transition.duration'),
            outline-color dt('tabs.transition.duration'),
            box-shadow dt('tabs.transition.duration');
        margin: dt('tabs.tab.margin');
        outline-color: transparent;
    }

    .p-tab:not(.p-disabled):focus-visible {
        z-index: 1;
        box-shadow: dt('tabs.tab.focus.ring.shadow');
        outline: dt('tabs.tab.focus.ring.width') dt('tabs.tab.focus.ring.style') dt('tabs.tab.focus.ring.color');
        outline-offset: dt('tabs.tab.focus.ring.offset');
    }

    .p-tab:not(.p-tab-active):not(.p-disabled):hover {
        background: dt('tabs.tab.hover.background');
        border-color: dt('tabs.tab.hover.border.color');
        color: dt('tabs.tab.hover.color');
    }

    .p-tab-active {
        background: dt('tabs.tab.active.background');
        border-color: dt('tabs.tab.active.border.color');
        color: dt('tabs.tab.active.color');
    }

    .p-tabpanels {
        background: dt('tabs.tabpanel.background');
        color: dt('tabs.tabpanel.color');
        padding: dt('tabs.tabpanel.padding');
        outline: 0 none;
    }

    .p-tabpanel:focus-visible {
        box-shadow: dt('tabs.tabpanel.focus.ring.shadow');
        outline: dt('tabs.tabpanel.focus.ring.width') dt('tabs.tabpanel.focus.ring.style') dt('tabs.tabpanel.focus.ring.color');
        outline-offset: dt('tabs.tabpanel.focus.ring.offset');
    }

    .p-tablist-active-bar {
        z-index: 1;
        display: block;
        position: absolute;
        inset-block-end: dt('tabs.active.bar.bottom');
        height: dt('tabs.active.bar.height');
        background: dt('tabs.active.bar.background');
        transition: 250ms cubic-bezier(0.35, 0, 0.25, 1);
    }
`,Wt={root:function(t){var i=t.props;return["p-tabs p-component",{"p-tabs-scrollable":i.scrollable}]}},Ht=q.extend({name:"tabs",style:Mt,classes:Wt}),Zt={name:"BaseTabs",extends:Q,props:{value:{type:[String,Number],default:void 0},lazy:{type:Boolean,default:!1},scrollable:{type:Boolean,default:!1},showNavigators:{type:Boolean,default:!0},tabindex:{type:Number,default:0},selectOnFocus:{type:Boolean,default:!1}},style:Ht,provide:function(){return{$pcTabs:this,$parentInstance:this}}},gt={name:"Tabs",extends:Zt,inheritAttrs:!1,emits:["update:value"],data:function(){return{d_value:this.value}},watch:{value:function(t){this.d_value=t}},methods:{updateValue:function(t){this.d_value!==t&&(this.d_value=t,this.$emit("update:value",t))},isVertical:function(){return this.orientation==="vertical"}}};function qt(e,t,i,n,d,o){return v(),g("div",x({class:e.cx("root")},e.ptmi("root")),[V(e.$slots,"default")],16)}gt.render=qt;var Qt={root:"p-tablist",content:"p-tablist-content p-tablist-viewport",tabList:"p-tablist-tab-list",activeBar:"p-tablist-active-bar",prevButton:"p-tablist-prev-button p-tablist-nav-button",nextButton:"p-tablist-next-button p-tablist-nav-button"},Gt=q.extend({name:"tablist",classes:Qt}),Jt={name:"BaseTabList",extends:Q,props:{},style:Gt,provide:function(){return{$pcTabList:this,$parentInstance:this}}},yt={name:"TabList",extends:Jt,inheritAttrs:!1,inject:["$pcTabs"],data:function(){return{isPrevButtonEnabled:!1,isNextButtonEnabled:!0}},resizeObserver:void 0,inkBarObserver:void 0,watch:{showNavigators:function(t){t?this.bindResizeObserver():this.unbindResizeObserver()},activeValue:{flush:"post",handler:function(){this.updateInkBar(),this.bindInkBarObserver()}}},mounted:function(){var t=this;setTimeout(function(){t.updateInkBar(),t.bindInkBarObserver()},150),this.showNavigators&&(this.updateButtonState(),this.bindResizeObserver())},updated:function(){this.showNavigators&&this.updateButtonState()},beforeUnmount:function(){this.unbindResizeObserver(),this.unbindInkBarObserver()},methods:{onScroll:function(t){this.showNavigators&&this.updateButtonState(),t.preventDefault()},onPrevButtonClick:function(){var t=this.$refs.content,i=this.getVisibleButtonWidths(),n=et(t)-i,d=Math.abs(t.scrollLeft),o=n*.8,y=d-o,k=Math.max(y,0);t.scrollLeft=ct(t)?-1*k:k},onNextButtonClick:function(){var t=this.$refs.content,i=this.getVisibleButtonWidths(),n=et(t)-i,d=Math.abs(t.scrollLeft),o=n*.8,y=d+o,k=t.scrollWidth-n,T=Math.min(y,k);t.scrollLeft=ct(t)?-1*T:T},bindResizeObserver:function(){var t=this;this.resizeObserver=new ResizeObserver(function(){return t.updateButtonState()}),this.resizeObserver.observe(this.$refs.list)},unbindResizeObserver:function(){var t;(t=this.resizeObserver)===null||t===void 0||t.unobserve(this.$refs.list),this.resizeObserver=void 0},bindInkBarObserver:function(){var t=this;this.unbindInkBarObserver();var i=this.$refs.content,n=Z(i,'[data-pc-name="tab"][data-p-active="true"]');n&&(this.inkBarObserver=new ResizeObserver(function(){return t.updateInkBar()}),this.inkBarObserver.observe(n))},unbindInkBarObserver:function(){var t;(t=this.inkBarObserver)===null||t===void 0||t.disconnect(),this.inkBarObserver=void 0},updateInkBar:function(){var t=this.$refs,i=t.content,n=t.inkbar,d=t.tabs;if(n){var o=Z(i,'[data-pc-name="tab"][data-p-active="true"]');this.$pcTabs.isVertical()?(n.style.height=Ct(o)+"px",n.style.top=M(o).top-M(d).top+"px"):(n.style.width=Nt(o)+"px",n.style.left=M(o).left-M(d).left+"px")}},updateButtonState:function(){var t=this.$refs,i=t.list,n=t.content,d=n.scrollTop,o=n.scrollWidth,y=n.scrollHeight,k=n.offsetWidth,T=n.offsetHeight,A=Math.abs(n.scrollLeft),K=[et(n),St(n)],G=K[0],J=K[1];this.$pcTabs.isVertical()?(this.isPrevButtonEnabled=d!==0,this.isNextButtonEnabled=i.offsetHeight>=T&&parseInt(d)!==y-J):(this.isPrevButtonEnabled=A!==0,this.isNextButtonEnabled=i.offsetWidth>=k&&parseInt(A)!==o-G)},getVisibleButtonWidths:function(){var t=this.$refs,i=t.prevButton,n=t.nextButton,d=0;return this.showNavigators&&(d=((i==null?void 0:i.offsetWidth)||0)+((n==null?void 0:n.offsetWidth)||0)),d}},computed:{templates:function(){return this.$pcTabs.$slots},activeValue:function(){return this.$pcTabs.d_value},showNavigators:function(){return this.$pcTabs.showNavigators},prevButtonAriaLabel:function(){return this.$primevue.config.locale.aria?this.$primevue.config.locale.aria.previous:void 0},nextButtonAriaLabel:function(){return this.$primevue.config.locale.aria?this.$primevue.config.locale.aria.next:void 0},dataP:function(){return ht({scrollable:this.$pcTabs.scrollable})}},components:{ChevronLeftIcon:Bt,ChevronRightIcon:$t},directives:{ripple:ft}},Xt=["data-p"],Yt=["aria-label","tabindex"],te=["data-p"],ee=["aria-orientation"],ae=["aria-label","tabindex"];function se(e,t,i,n,d,o){var y=mt("ripple");return v(),g("div",x({ref:"list",class:e.cx("root"),"data-p":o.dataP},e.ptmi("root")),[o.showNavigators&&d.isPrevButtonEnabled?E((v(),g("button",x({key:0,ref:"prevButton",type:"button",class:e.cx("prevButton"),"aria-label":o.prevButtonAriaLabel,tabindex:o.$pcTabs.tabindex,onClick:t[0]||(t[0]=function(){return o.onPrevButtonClick&&o.onPrevButtonClick.apply(o,arguments)})},e.ptm("prevButton"),{"data-pc-group-section":"navigator"}),[(v(),_(it(o.templates.previcon||"ChevronLeftIcon"),x({"aria-hidden":"true"},e.ptm("prevIcon")),null,16))],16,Yt)),[[y]]):N("",!0),s("div",x({ref:"content",class:e.cx("content"),onScroll:t[1]||(t[1]=function(){return o.onScroll&&o.onScroll.apply(o,arguments)}),"data-p":o.dataP},e.ptm("content")),[s("div",x({ref:"tabs",class:e.cx("tabList"),role:"tablist","aria-orientation":o.$pcTabs.orientation||"horizontal"},e.ptm("tabList")),[V(e.$slots,"default"),s("span",x({ref:"inkbar",class:e.cx("activeBar"),role:"presentation","aria-hidden":"true"},e.ptm("activeBar")),null,16)],16,ee)],16,te),o.showNavigators&&d.isNextButtonEnabled?E((v(),g("button",x({key:1,ref:"nextButton",type:"button",class:e.cx("nextButton"),"aria-label":o.nextButtonAriaLabel,tabindex:o.$pcTabs.tabindex,onClick:t[2]||(t[2]=function(){return o.onNextButtonClick&&o.onNextButtonClick.apply(o,arguments)})},e.ptm("nextButton"),{"data-pc-group-section":"navigator"}),[(v(),_(it(o.templates.nexticon||"ChevronRightIcon"),x({"aria-hidden":"true"},e.ptm("nextIcon")),null,16))],16,ae)),[[y]]):N("",!0)],16,Xt)}yt.render=se;var ne={root:function(t){var i=t.instance,n=t.props;return["p-tab",{"p-tab-active":i.active,"p-disabled":n.disabled}]}},ie=q.extend({name:"tab",classes:ne}),oe={name:"BaseTab",extends:Q,props:{value:{type:[String,Number],default:void 0},disabled:{type:Boolean,default:!1},as:{type:[String,Object],default:"BUTTON"},asChild:{type:Boolean,default:!1}},style:ie,provide:function(){return{$pcTab:this,$parentInstance:this}}},ot={name:"Tab",extends:oe,inheritAttrs:!1,inject:["$pcTabs","$pcTabList"],methods:{onFocus:function(){this.$pcTabs.selectOnFocus&&this.changeActiveValue()},onClick:function(){this.changeActiveValue()},onKeydown:function(t){switch(t.code){case"ArrowRight":this.onArrowRightKey(t);break;case"ArrowLeft":this.onArrowLeftKey(t);break;case"Home":this.onHomeKey(t);break;case"End":this.onEndKey(t);break;case"PageDown":this.onPageDownKey(t);break;case"PageUp":this.onPageUpKey(t);break;case"Enter":case"NumpadEnter":case"Space":this.onEnterKey(t);break}},onArrowRightKey:function(t){var i=this.findNextTab(t.currentTarget);i?this.changeFocusedTab(t,i):this.onHomeKey(t),t.preventDefault()},onArrowLeftKey:function(t){var i=this.findPrevTab(t.currentTarget);i?this.changeFocusedTab(t,i):this.onEndKey(t),t.preventDefault()},onHomeKey:function(t){var i=this.findFirstTab();this.changeFocusedTab(t,i),t.preventDefault()},onEndKey:function(t){var i=this.findLastTab();this.changeFocusedTab(t,i),t.preventDefault()},onPageDownKey:function(t){this.scrollInView(this.findLastTab()),t.preventDefault()},onPageUpKey:function(t){this.scrollInView(this.findFirstTab()),t.preventDefault()},onEnterKey:function(t){this.changeActiveValue()},findNextTab:function(t){var i=arguments.length>1&&arguments[1]!==void 0?arguments[1]:!1,n=i?t:t.nextElementSibling;return n?W(n,"data-p-disabled")||W(n,"data-pc-section")==="activebar"?this.findNextTab(n):Z(n,'[data-pc-name="tab"]'):null},findPrevTab:function(t){var i=arguments.length>1&&arguments[1]!==void 0?arguments[1]:!1,n=i?t:t.previousElementSibling;return n?W(n,"data-p-disabled")||W(n,"data-pc-section")==="activebar"?this.findPrevTab(n):Z(n,'[data-pc-name="tab"]'):null},findFirstTab:function(){return this.findNextTab(this.$pcTabList.$refs.tabs.firstElementChild,!0)},findLastTab:function(){return this.findPrevTab(this.$pcTabList.$refs.tabs.lastElementChild,!0)},changeActiveValue:function(){this.$pcTabs.updateValue(this.value)},changeFocusedTab:function(t,i){At(i),this.scrollInView(i)},scrollInView:function(t){var i;t==null||(i=t.scrollIntoView)===null||i===void 0||i.call(t,{block:"nearest"})}},computed:{active:function(){var t;return Ot((t=this.$pcTabs)===null||t===void 0?void 0:t.d_value,this.value)},id:function(){var t;return"".concat((t=this.$pcTabs)===null||t===void 0?void 0:t.$id,"_tab_").concat(this.value)},ariaControls:function(){var t;return"".concat((t=this.$pcTabs)===null||t===void 0?void 0:t.$id,"_tabpanel_").concat(this.value)},attrs:function(){return x(this.asAttrs,this.a11yAttrs,this.ptmi("root",this.ptParams))},asAttrs:function(){return this.as==="BUTTON"?{type:"button",disabled:this.disabled}:void 0},a11yAttrs:function(){return{id:this.id,tabindex:this.active?this.$pcTabs.tabindex:-1,role:"tab","aria-selected":this.active,"aria-controls":this.ariaControls,"data-pc-name":"tab","data-p-disabled":this.disabled,"data-p-active":this.active,onFocus:this.onFocus,onKeydown:this.onKeydown}},ptParams:function(){return{context:{active:this.active}}},dataP:function(){return ht({active:this.active})}},directives:{ripple:ft}};function re(e,t,i,n,d,o){var y=mt("ripple");return e.asChild?V(e.$slots,"default",{key:1,dataP:o.dataP,class:zt(e.cx("root")),active:o.active,a11yAttrs:o.a11yAttrs,onClick:o.onClick}):E((v(),_(it(e.as),x({key:0,class:e.cx("root"),"data-p":o.dataP,onClick:o.onClick},o.attrs),{default:f(function(){return[V(e.$slots,"default")]}),_:3},16,["class","data-p","onClick"])),[[y]])}ot.render=re;var le={root:"p-tabpanels"},de=q.extend({name:"tabpanels",classes:le}),ce={name:"BaseTabPanels",extends:Q,props:{},style:de,provide:function(){return{$pcTabPanels:this,$parentInstance:this}}},xt={name:"TabPanels",extends:ce,inheritAttrs:!1};function ue(e,t,i,n,d,o){return v(),g("div",x({class:e.cx("root"),role:"presentation"},e.ptmi("root")),[V(e.$slots,"default")],16)}xt.render=ue;const be={class:"grid grid-cols-12 gap-4"},ve={class:"col-span-12"},pe={class:"card mb-0"},fe={class:"flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4"},he={class:"flex flex-col md:flex-row gap-3 md:items-center"},me={key:1,class:"text-sm text-muted-color mt-2 mb-0"},ge={class:"card mb-0"},ye={class:"col-span-12 md:col-span-6 xl:col-span-3"},xe={class:"card mb-0"},we={class:"flex justify-between"},ke={class:"col-span-12 md:col-span-6 xl:col-span-3"},_e={class:"card mb-0"},Te={class:"flex justify-between"},$e={class:"text-surface-900 dark:text-surface-0 font-medium text-lg"},Be={class:"col-span-12 md:col-span-6 xl:col-span-3"},Se={class:"card mb-0"},Ce={class:"flex justify-between"},Ne={class:"text-surface-900 dark:text-surface-0 font-medium text-xl"},Oe={class:"text-muted-color text-base"},Ae={class:"col-span-12 md:col-span-6 xl:col-span-3"},ze={class:"card mb-0"},Ie={class:"flex justify-between"},Pe={class:"text-surface-900 dark:text-surface-0 font-medium text-xl"},Le={class:"col-span-12"},Ve={class:"card"},Ee={class:"font-medium"},Ke={class:"text-xs text-muted-color"},Re={key:1,class:"text-red-500 text-sm"},Fe={key:0,class:"text-sm text-muted-color mb-3"},Ge={__name:"SincronizacionCatalogos",setup(e){const t=It(),i=m(!1),n=m([]),d=m({sucursal_id:null,punto_venta_id:null}),o=async()=>{var r,a,b;i.value=!0;try{const p=await Ut.getBrancPointOfSales();n.value=((r=p==null?void 0:p.data)==null?void 0:r.data)??(p==null?void 0:p.data)??[],console.log("data sucursales: ",n.value),n.value.length&&(d.value.sucursal_id=n.value[0].id)}catch(p){console.error("Error cargando sucursales:",p),n.value=[],t.add({severity:"error",summary:"Error",detail:((b=(a=p==null?void 0:p.response)==null?void 0:a.data)==null?void 0:b.message)||"No se pudieron cargar las sucursales.",life:4e3})}finally{i.value=!1}},y=B(()=>n.value.find(r=>String(r.id)===String(d.value.sucursal_id))||null),k=B(()=>{var r;return((r=y.value)==null?void 0:r.point_of_sales)??[]}),T=B(()=>k.value.find(r=>String(r.id)===String(d.value.punto_venta_id))||null);Pt(()=>d.value.sucursal_id,()=>{var r;d.value.punto_venta_id=((r=k.value[0])==null?void 0:r.id)??null});const A=B(()=>T.value.id),K={actividades:"Actividades económicas",productos_servicios:"Productos y servicios",leyendas:"Leyendas de factura",mensajes:"Mensajes de servicios",eventos:"Eventos significativos",motivos_anulacion:"Motivos de anulación",documentos_identidad:"Documentos de identidad",documentos_sector:"Documentos sector",tipos_emision:"Tipos de emisión",tipos_habitacion:"Tipos de habitación",metodos_pago:"Métodos de pago",monedas:"Monedas",tipos_punto_venta:"Tipos de punto de venta",tipos_factura:"Tipos de factura",unidades:"Unidades de medida",paises:"Países de origen"},G={OK:"success",PARCIAL:"warn",ERROR:"danger",NUNCA_SINCRONIZADO:"secondary"},J={OK:"Sincronizado",PARCIAL:"Parcial",ERROR:"Con errores",NUNCA_SINCRONIZADO:"Sin sincronizar"},z=m(!0),O=m(null),R=m(null),F=B(()=>{var r;return((r=O.value)==null?void 0:r.estado_general)??"NUNCA_SINCRONIZADO"}),D=B(()=>{var r;return((r=O.value)==null?void 0:r.resumen)??{total:0,exitosos:0,fallidos:0,porcentaje:0}}),rt=(r,a)=>Object.entries(r??{}).map(([b,p])=>({clave:b,tipo:a,etiqueta:K[b]??b,...p})),wt=B(()=>{var r,a;return[{valor:"catalogos",filas:rt((r=O.value)==null?void 0:r.catalogos,"catalogo")},{valor:"parametros",filas:rt((a=O.value)==null?void 0:a.parametros,"parametro")}]});async function X(){var r,a;z.value=!0,R.value=null;try{const b=await H.estado();O.value=b.data}catch(b){R.value=((a=(r=b.response)==null?void 0:r.data)==null?void 0:a.message)??"No se pudo cargar el estado."}finally{z.value=!1}}const Y=r=>r?new Date(r.replace(" ","T")).toLocaleString("es-BO",{dateStyle:"medium",timeStyle:"short"}):"—",$=m(!1);async function kt(){var r,a;if(!T.value){t.add({severity:"warn",summary:"Seleccione un punto de venta",detail:"Elija sucursal y punto de venta antes de sincronizar.",life:4e3});return}$.value=!0;try{console.log("codigo punto deventa: ",A.value);const b=await H.sincronizar(A.value);t.add({severity:b.estado_general==="OK"?"success":"warn",summary:b.estado_general==="OK"?"Sincronización completa":"Sincronización parcial",detail:b.message,life:5e3})}catch(b){t.add({severity:"error",summary:"No se pudo sincronizar",detail:((a=(r=b.response)==null?void 0:r.data)==null?void 0:a.message)??"Error de conexión con el servidor.",life:6e3})}finally{$.value=!1,X()}}const j=m(!1),tt=m(!1),lt=m(""),I=m({}),U=m([]),P=m({global:{value:null,matchMode:Ft.CONTAINS}}),dt=B(()=>{const r=U.value[0];return r?Object.keys(r):[]});async function _t(r){var a,b;j.value=!0,tt.value=!0,lt.value=r.etiqueta,U.value=[],I.value={},P.value.global.value=null;try{const p=r.tipo==="catalogo"?await H.catalogo(r.clave):await H.parametro(r.clave);U.value=p.data??[],I.value=p.meta??{}}catch(p){j.value=!1,t.add({severity:"error",summary:"No se pudo leer",detail:((b=(a=p.response)==null?void 0:a.data)==null?void 0:b.message)??"Error al consultar los datos.",life:5e3})}finally{tt.value=!1}}return Lt(()=>{o(),X()}),(r,a)=>{var p;const b=Tt;return v(),g(L,null,[s("div",be,[s("div",ve,[s("div",pe,[s("div",fe,[a[5]||(a[5]=s("div",null,[s("h2",{class:"text-2xl font-semibold m-0"},"Catálogos y parámetros SIAT"),s("p",{class:"text-muted-color mt-1 mb-0"}," Información sincronizada con Impuestos Nacionales para la emisión de facturas. ")],-1)),s("div",he,[c(l(ut),{modelValue:d.value.sucursal_id,"onUpdate:modelValue":a[0]||(a[0]=h=>d.value.sucursal_id=h),options:n.value,optionLabel:"branch_name",optionValue:"id",placeholder:"Sucursal",loading:i.value,disabled:$.value,class:"w-full md:w-56"},null,8,["modelValue","options","loading","disabled"]),c(l(ut),{modelValue:d.value.punto_venta_id,"onUpdate:modelValue":a[1]||(a[1]=h=>d.value.punto_venta_id=h),options:k.value,optionLabel:"name",optionValue:"id",placeholder:"Punto de venta",disabled:!d.value.sucursal_id||$.value,class:"w-full md:w-56"},null,8,["modelValue","options","disabled"]),c(l(at),{label:"Sincronizar",icon:"pi pi-sync",loading:$.value,disabled:!T.value,onClick:kt},null,8,["loading","disabled"]),E(c(l(at),{icon:"pi pi-refresh",severity:"secondary",outlined:"",disabled:$.value,onClick:X},null,8,["disabled"]),[[b,"Actualizar estado",void 0,{top:!0}]])])]),$.value?(v(),_(l(vt),{key:0,mode:"indeterminate",style:{height:"4px"},class:"mt-4"})):N("",!0),$.value?(v(),g("p",me," Consultando el SIAT, esto puede tardar unos segundos… ")):N("",!0)])]),R.value?(v(),_(l(pt),{key:0,severity:"error",class:"col-span-12",closable:!1},{default:f(()=>[S(w(R.value),1)]),_:1})):N("",!0),z.value?(v(),g(L,{key:1},st(4,h=>s("div",{key:h,class:"col-span-12 md:col-span-6 xl:col-span-3"},[s("div",ge,[c(l(jt),{height:"4.5rem"})])])),64)):(v(),g(L,{key:2},[s("div",ye,[s("div",xe,[s("div",we,[s("div",null,[a[6]||(a[6]=s("span",{class:"block text-muted-color font-medium mb-3"},"Estado general",-1)),c(l(nt),{value:J[F.value]??F.value,severity:G[F.value]??"secondary",class:"text-base"},null,8,["value","severity"])]),a[7]||(a[7]=s("div",{class:"flex items-center justify-center bg-blue-100 dark:bg-blue-400/10 rounded-border",style:{width:"2.5rem",height:"2.5rem"}},[s("i",{class:"pi pi-shield text-blue-500 text-xl"})],-1))])])]),s("div",ke,[s("div",_e,[s("div",Te,[s("div",null,[a[8]||(a[8]=s("span",{class:"block text-muted-color font-medium mb-3"},"Última sincronización",-1)),s("div",$e,w(Y((p=O.value)==null?void 0:p.ultima_sincronizacion)),1)]),a[9]||(a[9]=s("div",{class:"flex items-center justify-center bg-orange-100 dark:bg-orange-400/10 rounded-border",style:{width:"2.5rem",height:"2.5rem"}},[s("i",{class:"pi pi-clock text-orange-500 text-xl"})],-1))])])]),s("div",Be,[s("div",Se,[s("div",Ce,[s("div",null,[a[10]||(a[10]=s("span",{class:"block text-muted-color font-medium mb-3"},"Correctos",-1)),s("div",Ne,[S(w(D.value.exitosos)+" ",1),s("span",Oe,"/ "+w(D.value.total),1)])]),a[11]||(a[11]=s("div",{class:"flex items-center justify-center bg-green-100 dark:bg-green-400/10 rounded-border",style:{width:"2.5rem",height:"2.5rem"}},[s("i",{class:"pi pi-check-circle text-green-500 text-xl"})],-1))]),c(l(vt),{value:D.value.porcentaje??0,showValue:!1,style:{height:"6px"},class:"mt-3"},null,8,["value"])])]),s("div",Ae,[s("div",ze,[s("div",Ie,[s("div",null,[a[12]||(a[12]=s("span",{class:"block text-muted-color font-medium mb-3"},"Con errores",-1)),s("div",Pe,w(D.value.fallidos),1)]),a[13]||(a[13]=s("div",{class:"flex items-center justify-center bg-red-100 dark:bg-red-400/10 rounded-border",style:{width:"2.5rem",height:"2.5rem"}},[s("i",{class:"pi pi-exclamation-triangle text-red-500 text-xl"})],-1))])])])],64)),s("div",Le,[s("div",Ve,[!z.value&&F.value==="NUNCA_SINCRONIZADO"?(v(),_(l(pt),{key:0,severity:"info",closable:!1,class:"mb-4"},{default:f(()=>[...a[14]||(a[14]=[S(" Aún no se han sincronizado los catálogos. Presione ",-1),s("b",null,"Sincronizar",-1),S(" para descargarlos del SIAT. ",-1)])]),_:1})):N("",!0),c(l(gt),{value:"catalogos"},{default:f(()=>[c(l(yt),null,{default:f(()=>[c(l(ot),{value:"catalogos"},{default:f(()=>[...a[15]||(a[15]=[s("i",{class:"pi pi-book mr-2"},null,-1),S("Catálogos",-1)])]),_:1}),c(l(ot),{value:"parametros"},{default:f(()=>[...a[16]||(a[16]=[s("i",{class:"pi pi-sliders-h mr-2"},null,-1),S("Parámetros",-1)])]),_:1})]),_:1}),c(l(xt),null,{default:f(()=>[(v(!0),g(L,null,st(wt.value,h=>(v(),_(l(Dt),{key:h.valor,value:h.valor},{default:f(()=>[c(l(bt),{value:h.filas,loading:z.value,stripedRows:"",dataKey:"clave"},{empty:f(()=>[...a[17]||(a[17]=[s("div",{class:"text-center text-muted-color py-6"},"Sin información sincronizada.",-1)])]),default:f(()=>[c(l(C),{field:"etiqueta",header:"Nombre"},{body:f(({data:u})=>[s("div",Ee,w(u.etiqueta),1),s("div",Ke,w(u.clave),1)]),_:1}),c(l(C),{header:"Estado"},{body:f(({data:u})=>[c(l(nt),{value:u.success?"OK":"Error",severity:u.success?"success":"danger"},null,8,["value","severity"])]),_:1}),c(l(C),{field:"cantidad",header:"Registros",style:{width:"8rem"}}),c(l(C),{header:"Cambios"},{body:f(({data:u})=>[u.success?(v(),_(l(nt),{key:0,value:u.cambio_detectado?"Actualizado":"Sin cambios",severity:u.cambio_detectado?"info":"secondary"},null,8,["value","severity"])):(v(),g("span",Re,w(u.message),1))]),_:1}),c(l(C),{header:"Fecha"},{body:f(({data:u})=>[S(w(Y(u.fecha)),1)]),_:1}),c(l(C),{header:"",style:{width:"6rem"},alignFrozen:"right"},{body:f(({data:u})=>[E(c(l(at),{icon:"pi pi-eye",severity:"secondary",rounded:"",text:"",disabled:!u.success,onClick:De=>_t(u)},null,8,["disabled","onClick"]),[[b,"Ver datos",void 0,{top:!0}]])]),_:1})]),_:1},8,["value","loading"])]),_:2},1032,["value"]))),128))]),_:1})]),_:1})])])]),c(l(Rt),{visible:j.value,"onUpdate:visible":a[4]||(a[4]=h=>j.value=h),header:lt.value,modal:"",maximizable:"",style:{width:"85vw"},breakpoints:{"960px":"95vw"}},{default:f(()=>{var h;return[(h=I.value)!=null&&h.fecha_sincronizacion?(v(),g("div",Fe,w(I.value.cantidad)+" registros · sincronizado el "+w(Y(I.value.fecha_sincronizacion)),1)):N("",!0),c(l(bt),{value:U.value,loading:tt.value,filters:P.value,"onUpdate:filters":a[3]||(a[3]=u=>P.value=u),globalFilterFields:dt.value,paginator:"",rows:10,rowsPerPageOptions:[10,25,50,100],scrollable:"",scrollHeight:"55vh",stripedRows:"",size:"small"},{header:f(()=>[c(l(Vt),null,{default:f(()=>[c(l(Et),{class:"pi pi-search"}),c(l(Kt),{modelValue:P.value.global.value,"onUpdate:modelValue":a[2]||(a[2]=u=>P.value.global.value=u),placeholder:"Buscar…",class:"w-full sm:w-80"},null,8,["modelValue"])]),_:1})]),empty:f(()=>[...a[18]||(a[18]=[s("div",{class:"text-center text-muted-color py-6"},"No hay registros.",-1)])]),default:f(()=>[(v(!0),g(L,null,st(dt.value,u=>(v(),_(l(C),{key:u,field:u,header:u,style:{"min-width":"9rem"}},null,8,["field","header"]))),128))]),_:1},8,["value","loading","filters","globalFilterFields"])]}),_:1},8,["visible","header"])],64)}}};export{Ge as default};
