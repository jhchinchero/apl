import{a as V}from"./accountingService-C71VhxGd.js";import{B as Q,aS as X,aT as Z,ax as ee,C as te,aU as P,aV as F,aa as ne,aW as C,aC as R,E as g,a0 as ae,o as v,c as y,a as i,l as D,a2 as j,z as O,a3 as E,i as k,F as K,r as M,t as p,aX as ie,p as re,j as _,H as oe,b as u,u as c,g as N,Y as q,w as m,A as T,L as S,K as h,n as U,J as se,M as le}from"./index-rSQBkoFA.js";import{s as $}from"./index-DEfECpRu.js";var de=`
    .p-tabview-tablist-container {
        position: relative;
    }

    .p-tabview-scrollable > .p-tabview-tablist-container {
        overflow: hidden;
    }

    .p-tabview-tablist-scroll-container {
        overflow-x: auto;
        overflow-y: hidden;
        scroll-behavior: smooth;
        scrollbar-width: none;
        overscroll-behavior: contain auto;
    }

    .p-tabview-tablist-scroll-container::-webkit-scrollbar {
        display: none;
    }

    .p-tabview-tablist {
        display: flex;
        margin: 0;
        padding: 0;
        list-style-type: none;
        flex: 1 1 auto;
        background: dt('tabview.tab.list.background');
        border: 1px solid dt('tabview.tab.list.border.color');
        border-width: 0 0 1px 0;
        position: relative;
    }

    .p-tabview-tab-header {
        cursor: pointer;
        user-select: none;
        display: flex;
        align-items: center;
        text-decoration: none;
        position: relative;
        overflow: hidden;
        border-style: solid;
        border-width: 0 0 1px 0;
        border-color: transparent transparent dt('tabview.tab.border.color') transparent;
        color: dt('tabview.tab.color');
        padding: 1rem 1.125rem;
        font-weight: 600;
        border-top-right-radius: dt('border.radius.md');
        border-top-left-radius: dt('border.radius.md');
        transition:
            color dt('tabview.transition.duration'),
            outline-color dt('tabview.transition.duration');
        margin: 0 0 -1px 0;
        outline-color: transparent;
    }

    .p-tabview-tablist-item:not(.p-disabled) .p-tabview-tab-header:focus-visible {
        outline: dt('focus.ring.width') dt('focus.ring.style') dt('focus.ring.color');
        outline-offset: -1px;
    }

    .p-tabview-tablist-item:not(.p-highlight):not(.p-disabled):hover > .p-tabview-tab-header {
        color: dt('tabview.tab.hover.color');
    }

    .p-tabview-tablist-item.p-highlight > .p-tabview-tab-header {
        color: dt('tabview.tab.active.color');
    }

    .p-tabview-tab-title {
        line-height: 1;
        white-space: nowrap;
    }

    .p-tabview-next-button,
    .p-tabview-prev-button {
        position: absolute;
        top: 0;
        margin: 0;
        padding: 0;
        z-index: 2;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: dt('tabview.nav.button.background');
        color: dt('tabview.nav.button.color');
        width: 2.5rem;
        border-radius: 0;
        outline-color: transparent;
        transition:
            color dt('tabview.transition.duration'),
            outline-color dt('tabview.transition.duration');
        box-shadow: dt('tabview.nav.button.shadow');
        border: none;
        cursor: pointer;
        user-select: none;
    }

    .p-tabview-next-button:focus-visible,
    .p-tabview-prev-button:focus-visible {
        outline: dt('focus.ring.width') dt('focus.ring.style') dt('focus.ring.color');
        outline-offset: dt('focus.ring.offset');
    }

    .p-tabview-next-button:hover,
    .p-tabview-prev-button:hover {
        color: dt('tabview.nav.button.hover.color');
    }

    .p-tabview-prev-button {
        left: 0;
    }

    .p-tabview-next-button {
        right: 0;
    }

    .p-tabview-panels {
        background: dt('tabview.tab.panel.background');
        color: dt('tabview.tab.panel.color');
        padding: 0.875rem 1.125rem 1.125rem 1.125rem;
    }

    .p-tabview-ink-bar {
        z-index: 1;
        display: block;
        position: absolute;
        bottom: -1px;
        height: 1px;
        background: dt('tabview.tab.active.border.color');
        transition: 250ms cubic-bezier(0.35, 0, 0.25, 1);
    }
`,ue={root:function(e){var a=e.props;return["p-tabview p-component",{"p-tabview-scrollable":a.scrollable}]},navContainer:"p-tabview-tablist-container",prevButton:"p-tabview-prev-button",navContent:"p-tabview-tablist-scroll-container",nav:"p-tabview-tablist",tab:{header:function(e){var a=e.instance,n=e.tab,l=e.index;return["p-tabview-tablist-item",a.getTabProp(n,"headerClass"),{"p-tabview-tablist-item-active":a.d_activeIndex===l,"p-disabled":a.getTabProp(n,"disabled")}]},headerAction:"p-tabview-tab-header",headerTitle:"p-tabview-tab-title",content:function(e){var a=e.instance,n=e.tab;return["p-tabview-panel",a.getTabProp(n,"contentClass")]}},inkbar:"p-tabview-ink-bar",nextButton:"p-tabview-next-button",panelContainer:"p-tabview-panels"},ce=Q.extend({name:"tabview",style:de,classes:ue}),be={name:"BaseTabView",extends:te,props:{activeIndex:{type:Number,default:0},lazy:{type:Boolean,default:!1},scrollable:{type:Boolean,default:!1},tabindex:{type:Number,default:0},selectOnFocus:{type:Boolean,default:!1},prevButtonProps:{type:null,default:null},nextButtonProps:{type:null,default:null},prevIcon:{type:String,default:void 0},nextIcon:{type:String,default:void 0}},style:ce,provide:function(){return{$pcTabs:void 0,$pcTabView:this,$parentInstance:this}}},G={name:"TabView",extends:be,inheritAttrs:!1,emits:["update:activeIndex","tab-change","tab-click"],data:function(){return{d_activeIndex:this.activeIndex,isPrevButtonDisabled:!0,isNextButtonDisabled:!1}},watch:{activeIndex:function(e){this.d_activeIndex=e,this.scrollInView({index:e})}},mounted:function(){console.warn("Deprecated since v4. Use Tabs component instead."),this.updateInkBar(),this.scrollable&&this.updateButtonState()},updated:function(){this.updateInkBar(),this.scrollable&&this.updateButtonState()},methods:{isTabPanel:function(e){return e.type.name==="TabPanel"},isTabActive:function(e){return this.d_activeIndex===e},getTabProp:function(e,a){return e.props?e.props[a]:void 0},getKey:function(e,a){return this.getTabProp(e,"header")||a},getTabHeaderActionId:function(e){return"".concat(this.$id,"_").concat(e,"_header_action")},getTabContentId:function(e){return"".concat(this.$id,"_").concat(e,"_content")},getTabPT:function(e,a,n){var l=this.tabs.length,o={props:e.props,parent:{instance:this,props:this.$props,state:this.$data},context:{index:n,count:l,first:n===0,last:n===l-1,active:this.isTabActive(n)}};return g(this.ptm("tabpanel.".concat(a),{tabpanel:o}),this.ptm("tabpanel.".concat(a),o),this.ptmo(this.getTabProp(e,"pt"),a,o))},onScroll:function(e){this.scrollable&&this.updateButtonState(),e.preventDefault()},onPrevButtonClick:function(){var e=this.$refs.content,a=P(e),n=e.scrollLeft-a;e.scrollLeft=n<=0?0:n},onNextButtonClick:function(){var e=this.$refs.content,a=P(e)-this.getVisibleButtonWidths(),n=e.scrollLeft+a,l=e.scrollWidth-a;e.scrollLeft=n>=l?l:n},onTabClick:function(e,a,n){this.changeActiveIndex(e,a,n),this.$emit("tab-click",{originalEvent:e,index:n})},onTabKeyDown:function(e,a,n){switch(e.code){case"ArrowLeft":this.onTabArrowLeftKey(e);break;case"ArrowRight":this.onTabArrowRightKey(e);break;case"Home":this.onTabHomeKey(e);break;case"End":this.onTabEndKey(e);break;case"PageDown":this.onPageDownKey(e);break;case"PageUp":this.onPageUpKey(e);break;case"Enter":case"NumpadEnter":case"Space":this.onTabEnterKey(e,a,n);break}},onTabArrowRightKey:function(e){var a=this.findNextHeaderAction(e.target.parentElement);a?this.changeFocusedTab(e,a):this.onTabHomeKey(e),e.preventDefault()},onTabArrowLeftKey:function(e){var a=this.findPrevHeaderAction(e.target.parentElement);a?this.changeFocusedTab(e,a):this.onTabEndKey(e),e.preventDefault()},onTabHomeKey:function(e){var a=this.findFirstHeaderAction();this.changeFocusedTab(e,a),e.preventDefault()},onTabEndKey:function(e){var a=this.findLastHeaderAction();this.changeFocusedTab(e,a),e.preventDefault()},onPageDownKey:function(e){this.scrollInView({index:this.$refs.nav.children.length-2}),e.preventDefault()},onPageUpKey:function(e){this.scrollInView({index:0}),e.preventDefault()},onTabEnterKey:function(e,a,n){this.changeActiveIndex(e,a,n),e.preventDefault()},findNextHeaderAction:function(e){var a=arguments.length>1&&arguments[1]!==void 0?arguments[1]:!1,n=a?e:e.nextElementSibling;return n?C(n,"data-p-disabled")||C(n,"data-pc-section")==="inkbar"?this.findNextHeaderAction(n):R(n,'[data-pc-section="headeraction"]'):null},findPrevHeaderAction:function(e){var a=arguments.length>1&&arguments[1]!==void 0?arguments[1]:!1,n=a?e:e.previousElementSibling;return n?C(n,"data-p-disabled")||C(n,"data-pc-section")==="inkbar"?this.findPrevHeaderAction(n):R(n,'[data-pc-section="headeraction"]'):null},findFirstHeaderAction:function(){return this.findNextHeaderAction(this.$refs.nav.firstElementChild,!0)},findLastHeaderAction:function(){return this.findPrevHeaderAction(this.$refs.nav.lastElementChild,!0)},changeActiveIndex:function(e,a,n){!this.getTabProp(a,"disabled")&&this.d_activeIndex!==n&&(this.d_activeIndex=n,this.$emit("update:activeIndex",n),this.$emit("tab-change",{originalEvent:e,index:n}),this.scrollInView({index:n}))},changeFocusedTab:function(e,a){if(a&&(ne(a),this.scrollInView({element:a}),this.selectOnFocus)){var n=parseInt(a.parentElement.dataset.pcIndex,10),l=this.tabs[n];this.changeActiveIndex(e,l,n)}},scrollInView:function(e){var a=e.element,n=e.index,l=n===void 0?-1:n,o=a||this.$refs.nav.children[l];o&&o.scrollIntoView&&o.scrollIntoView({block:"nearest"})},updateInkBar:function(){var e=this.$refs.nav.children[this.d_activeIndex];this.$refs.inkbar.style.width=P(e)+"px",this.$refs.inkbar.style.left=F(e).left-F(this.$refs.nav).left+"px"},updateButtonState:function(){var e=this.$refs.content,a=e.scrollLeft,n=e.scrollWidth,l=P(e);this.isPrevButtonDisabled=a===0,this.isNextButtonDisabled=parseInt(a)===n-l},getVisibleButtonWidths:function(){var e=this.$refs,a=e.prevBtn,n=e.nextBtn;return[a,n].reduce(function(l,o){return o?l+P(o):l},0)}},computed:{tabs:function(){var e=this;return this.$slots.default().reduce(function(a,n){return e.isTabPanel(n)?a.push(n):n.children&&n.children instanceof Array&&n.children.forEach(function(l){e.isTabPanel(l)&&a.push(l)}),a},[])},prevButtonAriaLabel:function(){return this.$primevue.config.locale.aria?this.$primevue.config.locale.aria.previous:void 0},nextButtonAriaLabel:function(){return this.$primevue.config.locale.aria?this.$primevue.config.locale.aria.next:void 0}},directives:{ripple:ee},components:{ChevronLeftIcon:Z,ChevronRightIcon:X}};function B(t){"@babel/helpers - typeof";return B=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(e){return typeof e}:function(e){return e&&typeof Symbol=="function"&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},B(t)}function z(t,e){var a=Object.keys(t);if(Object.getOwnPropertySymbols){var n=Object.getOwnPropertySymbols(t);e&&(n=n.filter(function(l){return Object.getOwnPropertyDescriptor(t,l).enumerable})),a.push.apply(a,n)}return a}function w(t){for(var e=1;e<arguments.length;e++){var a=arguments[e]!=null?arguments[e]:{};e%2?z(Object(a),!0).forEach(function(n){pe(t,n,a[n])}):Object.getOwnPropertyDescriptors?Object.defineProperties(t,Object.getOwnPropertyDescriptors(a)):z(Object(a)).forEach(function(n){Object.defineProperty(t,n,Object.getOwnPropertyDescriptor(a,n))})}return t}function pe(t,e,a){return(e=ve(e))in t?Object.defineProperty(t,e,{value:a,enumerable:!0,configurable:!0,writable:!0}):t[e]=a,t}function ve(t){var e=fe(t,"string");return B(e)=="symbol"?e:e+""}function fe(t,e){if(B(t)!="object"||!t)return t;var a=t[Symbol.toPrimitive];if(a!==void 0){var n=a.call(t,e);if(B(n)!="object")return n;throw new TypeError("@@toPrimitive must return a primitive value.")}return(e==="string"?String:Number)(t)}var me=["tabindex","aria-label"],he=["data-p-active","data-p-disabled","data-pc-index"],ge=["id","tabindex","aria-disabled","aria-selected","aria-controls","onClick","onKeydown"],ye=["tabindex","aria-label"],we=["id","aria-labelledby","data-pc-index","data-p-active"];function Te(t,e,a,n,l,o){var x=ae("ripple");return v(),y("div",g({class:t.cx("root"),role:"tablist"},t.ptmi("root")),[i("div",g({class:t.cx("navContainer")},t.ptm("navContainer")),[t.scrollable&&!l.isPrevButtonDisabled?D((v(),y("button",g({key:0,ref:"prevBtn",type:"button",class:t.cx("prevButton"),tabindex:t.tabindex,"aria-label":o.prevButtonAriaLabel,onClick:e[0]||(e[0]=function(){return o.onPrevButtonClick&&o.onPrevButtonClick.apply(o,arguments)})},w(w({},t.prevButtonProps),t.ptm("prevButton")),{"data-pc-group-section":"navbutton"}),[j(t.$slots,"previcon",{},function(){return[(v(),O(E(t.prevIcon?"span":"ChevronLeftIcon"),g({"aria-hidden":"true",class:t.prevIcon},t.ptm("prevIcon")),null,16,["class"]))]})],16,me)),[[x]]):k("",!0),i("div",g({ref:"content",class:t.cx("navContent"),onScroll:e[1]||(e[1]=function(){return o.onScroll&&o.onScroll.apply(o,arguments)})},t.ptm("navContent")),[i("ul",g({ref:"nav",class:t.cx("nav")},t.ptm("nav")),[(v(!0),y(K,null,M(o.tabs,function(s,b){return v(),y("li",g({key:o.getKey(s,b),style:o.getTabProp(s,"headerStyle"),class:t.cx("tab.header",{tab:s,index:b}),role:"presentation"},{ref_for:!0},w(w(w({},o.getTabProp(s,"headerProps")),o.getTabPT(s,"root",b)),o.getTabPT(s,"header",b)),{"data-pc-name":"tabpanel","data-p-active":l.d_activeIndex===b,"data-p-disabled":o.getTabProp(s,"disabled"),"data-pc-index":b}),[D((v(),y("a",g({id:o.getTabHeaderActionId(b),class:t.cx("tab.headerAction"),tabindex:o.getTabProp(s,"disabled")||!o.isTabActive(b)?-1:t.tabindex,role:"tab","aria-disabled":o.getTabProp(s,"disabled"),"aria-selected":o.isTabActive(b),"aria-controls":o.getTabContentId(b),onClick:function(A){return o.onTabClick(A,s,b)},onKeydown:function(A){return o.onTabKeyDown(A,s,b)}},{ref_for:!0},w(w({},o.getTabProp(s,"headerActionProps")),o.getTabPT(s,"headerAction",b))),[s.props&&s.props.header?(v(),y("span",g({key:0,class:t.cx("tab.headerTitle")},{ref_for:!0},o.getTabPT(s,"headerTitle",b)),p(s.props.header),17)):k("",!0),s.children&&s.children.header?(v(),O(E(s.children.header),{key:1})):k("",!0)],16,ge)),[[x]])],16,he)}),128)),i("li",g({ref:"inkbar",class:t.cx("inkbar"),role:"presentation","aria-hidden":"true"},t.ptm("inkbar")),null,16)],16)],16),t.scrollable&&!l.isNextButtonDisabled?D((v(),y("button",g({key:1,ref:"nextBtn",type:"button",class:t.cx("nextButton"),tabindex:t.tabindex,"aria-label":o.nextButtonAriaLabel,onClick:e[2]||(e[2]=function(){return o.onNextButtonClick&&o.onNextButtonClick.apply(o,arguments)})},w(w({},t.nextButtonProps),t.ptm("nextButton")),{"data-pc-group-section":"navbutton"}),[j(t.$slots,"nexticon",{},function(){return[(v(),O(E(t.nextIcon?"span":"ChevronRightIcon"),g({"aria-hidden":"true",class:t.nextIcon},t.ptm("nextIcon")),null,16,["class"]))]})],16,ye)),[[x]]):k("",!0)],16),i("div",g({class:t.cx("panelContainer")},t.ptm("panelContainer")),[(v(!0),y(K,null,M(o.tabs,function(s,b){return v(),y(K,{key:o.getKey(s,b)},[!t.lazy||o.isTabActive(b)?D((v(),y("div",g({key:0,id:o.getTabContentId(b),style:o.getTabProp(s,"contentStyle"),class:t.cx("tab.content",{tab:s}),role:"tabpanel","aria-labelledby":o.getTabHeaderActionId(b)},{ref_for:!0},w(w(w({},o.getTabProp(s,"contentProps")),o.getTabPT(s,"root",b)),o.getTabPT(s,"content",b)),{"data-pc-name":"tabpanel","data-pc-index":b,"data-p-active":l.d_activeIndex===b}),[(v(),O(E(s)))],16,we)),[[ie,t.lazy?!0:o.isTabActive(b)]]):k("",!0)],64)}),128))],16)],16)}G.render=Te;const xe={class:"card"},Ie={class:"flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6"},Ae={class:"flex flex-wrap gap-2"},Pe={class:"flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6"},ke={class:"flex flex-wrap gap-2"},_e={class:"col-12 md:col-4"},Se={class:"col-12 md:col-4"},Be={class:"col-12 md:col-4"},Ce={class:"statement-container"},De={class:"statement-header"},Oe={class:"statement-section"},Ee={class:"statement-total"},Ne={class:"statement-section mt-5"},Le={class:"statement-total"},Ke={class:"text-2xl font-bold"},He={class:"statement-container"},Ve={class:"statement-header"},Fe={class:"statement-section"},Re={class:"statement-total"},je={class:"statement-section mt-5"},Me={class:"statement-total"},qe={class:"statement-section mt-5"},Ue={class:"equity-result"},$e={class:"statement-total"},ze={class:"balance-summary mt-5"},Ge={class:"balance-line"},We={class:"balance-line"},Ye={class:"balance-line final"},Je={key:0,class:"text-green-500 mt-3 font-medium"},Qe={key:1,class:"text-red-500 mt-3 font-medium"},Xe={__name:"EstadosFinancieros",setup(t){const e=_(!1),a=_(0),n=_({start_date:new Date(new Date().getFullYear(),0,1),end_date:new Date}),l=_({income:[],expenses:[],total_income:0,total_expenses:0,net_income:0}),o=_({assets:[],liabilities:[],equity:[],total_assets:0,total_liabilities:0,total_equity:0,current_result:0}),x=le(()=>Number(o.value.total_assets||0)-(Number(o.value.total_liabilities||0)+Number(o.value.total_equity||0)));function s(f){return new Intl.NumberFormat("es-BO",{style:"currency",currency:"BOB",minimumFractionDigits:2}).format(Number(f||0))}function b(f){return f?new Date(f).toLocaleDateString("es-BO"):""}function I(f){if(!f)return null;const r=f.getFullYear(),d=String(f.getMonth()+1).padStart(2,"0"),J=String(f.getDate()).padStart(2,"0");return`${r}-${d}-${J}`}async function A(){var f;try{const r=await V.getIncomeStatement({start_date:I(n.value.start_date),end_date:I(n.value.end_date)}),d=((f=r.data)==null?void 0:f.data)||r.data;l.value={income:d.income||[],expenses:d.expenses||[],total_income:Number(d.total_income||0),total_expenses:Number(d.total_expenses||0),net_income:Number(d.net_income||0)}}catch{}}async function W(){var f;try{const r=await V.getBalanceSheet({start_date:I(n.value.start_date),end_date:I(n.value.end_date)}),d=((f=r.data)==null?void 0:f.data)||r.data;o.value={assets:d.assets||[],liabilities:d.liabilities||[],equity:d.equity||[],total_assets:Number(d.total_assets||0),total_liabilities:Number(d.total_liabilities||0),total_equity:Number(d.total_equity||0),current_result:Number(d.current_result||0)}}catch{}}async function L(){e.value=!0;try{await Promise.all([A(),W()])}finally{e.value=!1}}function Y(){}function H(){window.print()}return oe(()=>{L()}),(f,r)=>(v(),y("div",xe,[i("div",Ie,[r[3]||(r[3]=i("div",null,[i("h2",{class:"m-0"},"Estados Financieros"),i("p",{class:"text-500 mt-2 mb-0"},"Información financiera de la empresa")],-1)),i("div",Ae,[u(c(N),{icon:"pi pi-refresh",label:"Actualizar",severity:"secondary",outlined:"",loading:e.value,onClick:L},null,8,["loading"]),u(c(N),{icon:"pi pi-print",label:"Imprimir",onClick:H}),u(c(N),{icon:"pi pi-file-pdf",label:"PDF",severity:"danger",onClick:H})])]),i("div",Pe,[r[7]||(r[7]=i("div",null,null,-1)),i("div",ke,[i("div",_e,[r[4]||(r[4]=i("label",{class:"block font-medium mb-2"}," Desde ",-1)),u(c(q),{modelValue:n.value.start_date,"onUpdate:modelValue":r[0]||(r[0]=d=>n.value.start_date=d),dateFormat:"yy-mm-dd",showIcon:"",class:"w-full"},null,8,["modelValue"])]),i("div",Se,[r[5]||(r[5]=i("label",{class:"block font-medium mb-2"}," Hasta ",-1)),u(c(q),{modelValue:n.value.end_date,"onUpdate:modelValue":r[1]||(r[1]=d=>n.value.end_date=d),dateFormat:"yy-mm-dd",showIcon:"",class:"w-full"},null,8,["modelValue"])]),i("div",Be,[r[6]||(r[6]=i("label",{class:"block font-medium mb-2"}," Generar",-1)),u(c(N),{label:"Generar estados",icon:"pi pi-chart-bar",onClick:L})])])]),u(c(G),{activeIndex:a.value,"onUpdate:activeIndex":r[2]||(r[2]=d=>a.value=d),onTabChange:Y},{default:m(()=>[u(c($),{header:"Estado de Resultados"},{default:m(()=>[i("div",Ce,[i("div",De,[r[10]||(r[10]=i("h3",null,"ESTADO DE RESULTADOS",-1)),i("div",null,[r[8]||(r[8]=T(" Del ",-1)),i("strong",null,p(b(n.value.start_date)),1),r[9]||(r[9]=T(" al ",-1)),i("strong",null,p(b(n.value.end_date)),1)])]),i("div",Oe,[r[13]||(r[13]=i("div",{class:"section-title"},"INGRESOS",-1)),u(c(S),{value:l.value.income,responsiveLayout:"scroll",class:"p-datatable-sm statement-table"},{empty:m(()=>[...r[11]||(r[11]=[i("div",{class:"text-center p-3 text-500"},"No existen ingresos.",-1)])]),default:m(()=>[u(c(h),{field:"code",header:"Código",style:{width:"130px"}}),u(c(h),{field:"name",header:"Cuenta"}),u(c(h),{header:"Importe",style:{width:"200px","text-align":"right"}},{body:m(({data:d})=>[T(p(s(d.amount)),1)]),_:1})]),_:1},8,["value"]),i("div",Ee,[r[12]||(r[12]=i("span",null," TOTAL INGRESOS ",-1)),i("span",null,p(s(l.value.total_income)),1)])]),i("div",Ne,[r[16]||(r[16]=i("div",{class:"section-title"},"GASTOS",-1)),u(c(S),{value:l.value.expenses,responsiveLayout:"scroll",class:"p-datatable-sm statement-table"},{empty:m(()=>[...r[14]||(r[14]=[i("div",{class:"text-center p-3 text-500"},"No existen gastos.",-1)])]),default:m(()=>[u(c(h),{field:"code",header:"Código",style:{width:"130px"}}),u(c(h),{field:"name",header:"Cuenta"}),u(c(h),{header:"Importe",style:{width:"200px","text-align":"right"}},{body:m(({data:d})=>[T(p(s(d.amount)),1)]),_:1})]),_:1},8,["value"]),i("div",Le,[r[15]||(r[15]=i("span",null," TOTAL GASTOS ",-1)),i("span",null,p(s(l.value.total_expenses)),1)])]),i("div",{class:U(["result-box mt-5",l.value.net_income>=0?"result-positive":"result-negative"])},[i("div",null,p(l.value.net_income>=0?"UTILIDAD DEL PERÍODO":"PÉRDIDA DEL PERÍODO"),1),i("div",Ke,p(s(Math.abs(l.value.net_income))),1)],2)])]),_:1}),u(c($),{header:"Balance General"},{default:m(()=>[i("div",He,[i("div",Ve,[r[18]||(r[18]=i("h3",null,"BALANCE GENERAL",-1)),i("div",null,[r[17]||(r[17]=T(" Al ",-1)),i("strong",null,p(b(n.value.end_date)),1)])]),i("div",Fe,[r[20]||(r[20]=i("div",{class:"section-title"},"ACTIVOS",-1)),u(c(S),{value:o.value.assets,responsiveLayout:"scroll",class:"p-datatable-sm statement-table"},{default:m(()=>[u(c(h),{field:"code",header:"Código",style:{width:"130px"}}),u(c(h),{field:"name",header:"Cuenta"}),u(c(h),{header:"Saldo",style:{width:"200px","text-align":"right"}},{body:m(({data:d})=>[T(p(s(Math.abs(d.amount))),1)]),_:1})]),_:1},8,["value"]),i("div",Re,[r[19]||(r[19]=i("span",null," TOTAL ACTIVOS ",-1)),i("span",null,p(s(o.value.total_assets)),1)])]),i("div",je,[r[22]||(r[22]=i("div",{class:"section-title"},"PASIVOS",-1)),u(c(S),{value:o.value.liabilities,responsiveLayout:"scroll",class:"p-datatable-sm statement-table"},{default:m(()=>[u(c(h),{field:"code",header:"Código",style:{width:"130px"}}),u(c(h),{field:"name",header:"Cuenta"}),u(c(h),{header:"Saldo",style:{width:"200px","text-align":"right"}},{body:m(({data:d})=>[T(p(s(Math.abs(d.amount))),1)]),_:1})]),_:1},8,["value"]),i("div",Me,[r[21]||(r[21]=i("span",null," TOTAL PASIVOS ",-1)),i("span",null,p(s(o.value.total_liabilities)),1)])]),i("div",qe,[r[25]||(r[25]=i("div",{class:"section-title"},"PATRIMONIO",-1)),u(c(S),{value:o.value.equity,responsiveLayout:"scroll",class:"p-datatable-sm statement-table"},{default:m(()=>[u(c(h),{field:"code",header:"Código",style:{width:"130px"}}),u(c(h),{field:"name",header:"Cuenta"}),u(c(h),{header:"Saldo",style:{width:"200px","text-align":"right"}},{body:m(({data:d})=>[T(p(s(Math.abs(d.amount))),1)]),_:1})]),_:1},8,["value"]),i("div",Ue,[r[23]||(r[23]=i("span",null," Resultado del período ",-1)),i("span",null,p(s(Math.abs(o.value.current_result))),1)]),i("div",$e,[r[24]||(r[24]=i("span",null," TOTAL PATRIMONIO ",-1)),i("span",null,p(s(o.value.total_equity)),1)])]),i("div",ze,[i("div",Ge,[r[26]||(r[26]=i("span",null," TOTAL ACTIVOS ",-1)),i("strong",null,p(s(o.value.total_assets)),1)]),i("div",We,[r[27]||(r[27]=i("span",null," PASIVOS + PATRIMONIO ",-1)),i("strong",null,p(s(Number(o.value.total_liabilities)+Number(o.value.total_equity))),1)]),u(c(se)),i("div",Ye,[r[28]||(r[28]=i("span",null," DIFERENCIA CONTABLE ",-1)),i("strong",{class:U(Math.abs(x.value)<.01?"text-green-500":"text-red-500")},p(s(Math.abs(x.value))),3)]),Math.abs(x.value)<.01?(v(),y("div",Je,[...r[29]||(r[29]=[i("i",{class:"pi pi-check-circle mr-2"},null,-1),T(" El balance se encuentra cuadrado. ",-1)])])):(v(),y("div",Qe,[...r[30]||(r[30]=[i("i",{class:"pi pi-exclamation-triangle mr-2"},null,-1),T(" Existe una diferencia contable. ",-1)])]))])])]),_:1})]),_:1},8,["activeIndex"])]))}},nt=re(Xe,[["__scopeId","data-v-083d9435"]]);export{nt as default};
