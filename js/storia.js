var models = {};
                                          
models.current_nav =
'<span class="sr-only">(current)</span>';

models.effettua_la_ricerca =
"<div class='alert alert-info'>Effettua una ricerca{{label_cosa}}</div>";

models.nessun_risultato =
"<div class='alert alert-warning'>Nessun risultato</div>";

models.elemento_lista =
'<div class="border-bottom py-3 d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">'+
    '<div class="text-break">'+
        '<b>{{dato_principale}}</b>'+
        '{{contenuto}}'+
    '</div>'+
    '{{vedi_altro}}'+
'</div>';

models.icona_altro =
'<div class="d-flex align-items-center ms-sm-4 risultato" style="cursor:pointer" {{valori_chiave}}>'+
    '&gt;'+
'</div>';

models.contenuto_singolo =
'<div>{{chiave}}: {{valore}}</div>';

models.card_collapsed =
'<div class="card collapsed-card mt-2">'+
    '<div class="card-header">'+
        '<h3 class="card-title">{{card_title}}</h3>'+ 
        '<div class="card-tools">'+ 
            '<button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button>'+ 
        '</div>'+ 
    '</div>'+ 
    '<div class="card-body">'+ 
        '{{card_content}}'+ 
    '</div>'+ 
'</div>';
class PageManager {
    constructor() {

        this.ar_filtri          = {};
        this.aziende            = aziende_chiavi;
        this.ar_dati            = {};
        this.ar_dati['aziende'] = aziende;
        this.ar_dati['utenti']  = {};
        this.nav_attivo         = "utenti";
        this.valore_ricercato   = "";
        this.loading_el        = $('#loading');

        this.disegna_tabella();
        this.inizializza_menu();
        document.addEventListener('click', (e) => {
            const risultato = e.target.closest('.risultato');
            if (risultato) {
                this.handleRisultatoClick({ currentTarget: risultato });
            }
        });
    }

    render_models(json_data, modello)
    {
        var str = modello,
            obj = json_data;
        str = str.replace(/{{(\w+)}}/g, function (m, m1) {

            let ret = "";

            if(m1 in obj)
            {
                ret = obj[m1];
            }

            return ret;
        });
        return str;
    }

    inizializza_menu()
    {
        this.attiva_nav();

        $('.nav-link').on('click', (e) => {
            e.preventDefault();
            this.nav_attivo = $(e.currentTarget).data("quale");
            this.attiva_nav();
        });

        $('#btn_ricerca').on('click', (e) => {

            this.ricerca();
        });
    }

    handleRisultatoClick(e)
    {
        const el = $(e.currentTarget);
        this.ar_filtri = {};

        if(el.data('codazi') !== undefined)
        {
            this.ar_filtri['codazi'] = el.data('codazi');
        }

        if(el.data('coddip') !== undefined)
        {
            this.ar_filtri['coddip'] = el.data('coddip');
        }

        if(el.data('anno') !== undefined)
        {
            this.ar_filtri['anno'] = el.data('anno');
        }

        if(el.data('mese') !== undefined)
        {
            this.ar_filtri['mese'] = el.data('mese');
        }

        let prossimo = 'utenti';

        if(el.data('mese') !== undefined)
        {
            prossimo = 'cedolino';
        }
        else if(el.data('anno') !== undefined)
        {
            prossimo = 'anno';
        }
        else if(el.data('coddip') !== undefined)
        {
            prossimo = 'dettagli_utente';
        }

        this.ricerca_dettagli(prossimo);
    }

    attiva_nav()
    {
        $(".nav-link").removeClass("active");
        $("#nav-"+this.nav_attivo).addClass("active");
        this.toggle_filter();
    }

    disegna_tabella(quale="")
    {
        let tabella_da_disegnare = "";

        if(quale!="")
        {
            tabella_da_disegnare = quale;
        }else{
            tabella_da_disegnare = this.nav_attivo;
        }

        let ar_dati_att         = {};
        let dati_render         = {};
        /*
        dati_render.testata     = "";
        dati_render.contenuto   = "";
        */
        let dbcolprinc   = [];

        let ar_colonne      = {};

        let valore_chiave = [];

        switch(tabella_da_disegnare)
        {
            case "aziende":

                ar_colonne.CODAZI   = "ID";
                ar_colonne.RAGSOC   = "Azienda";
                ar_colonne.INIZIO   = "Inizio";
                ar_colonne.FINE     = "Fine";
                ar_colonne.PARTIVA  = "P.Iva";

                ar_dati_att = this.ar_dati['aziende'];

                dbcolprinc = ["RAGSOC"];

                valore_chiave = ["CODAZI"];

            break;
            case "utenti":

                ar_colonne.CODDIP       = "ID";
                ar_colonne.DATANAS      = "Data nascita";
                ar_colonne.DATAASSUNZ   = "Data assunzione";
                ar_colonne.DATALICEN    = "Data fine";
                /*
                ar_colonne.CODQUALIF    = "Qualifica";
                ar_colonne.CODLIVELLO    = "Livello";
                */

                ar_dati_att = this.ar_dati['utenti'];

                dbcolprinc = ["COGNOME","NOME"];

                valore_chiave = ["CODDIP","CODAZI"];

            break;
            case 'dettagli_utente':

                ar_colonne.eta                  = "Et&agrave;";
                ar_colonne.ORDINARIA            = "Retribuzione ordinaria";
                ar_colonne.IRPEF                = "Irpef";
                ar_colonne.TOTALE_COMPETENZE    = "Totale competenze";
                ar_colonne.TOTALE_RITENUTE      = "Totale ritenute";
                ar_colonne.NETTO                = "Netto";

                ar_dati_att = this.ar_dati['dettagli_utente'];

                dbcolprinc = ["ANNO"];

                valore_chiave = ["CODDIP","CODAZI","ANNO"];

                //this.show_breadcrumbs();

            break;
            case 'anno':

                ar_colonne.NETTO                = "Netto";
                ar_colonne.ORDINARIA            = "Retribuzione ordinaria";
                /*
                ar_colonne.TFR                  = "Accantomento Tfr";
                ar_colonne.COMPETENZE           = "Totale competenze";
                ar_colonne.DETRAZIONI           = "Totale detrazioni";
                */
                ar_colonne.BONUS                = "Bonus Una Tantum";
                ar_colonne.PREMIO               = "Premio";
                ar_colonne.PREMIO_RISULTATO     = "Premio di risultato";

                ar_dati_att = this.ar_dati['anno'];

                dbcolprinc = ["ANNO_MESE"];

                valore_chiave = ["CODDIP","CODAZI","ANNO","MESE"];

                //this.show_breadcrumbs();

            break;
            case 'cedolino':

                ar_colonne.IMPORTO        = "Valore";

                ar_dati_att = this.ar_dati['cedolino'];

                dbcolprinc = ["DESCRIZ"];

            break;
        }

        let lista = "";
        let dati_render_dato = {};
        let ar_chiave_valore = {};

        //console.log(ar_dati_att);

        for(let pos in ar_dati_att)
        {
            let az = ar_dati_att[pos];

            dati_render.dato_principale = "";

            for(let chiave in dbcolprinc)
            {
                dati_render.dato_principale += az[dbcolprinc[chiave]]+" ";
            }

            dati_render.contenuto       = "";
            dati_render.vedi_altro      = "";

            let dati_render_altro = {};
            dati_render_altro.valori_chiave   = "";

            for(let posk in valore_chiave)
            {
                let chiave = valore_chiave[posk];
                dati_render_altro.valori_chiave += "data-"+chiave+"="+az[chiave]+" ";
            }

            if(dati_render_altro.valori_chiave!="")
            {
                dati_render.vedi_altro = this.render_models(dati_render_altro, models.icona_altro);
            }

            /*CONTENUTO*/

            if(tabella_da_disegnare=="utenti")
            {
                dati_render.contenuto += "<br><i>"+this.aziende[az['CODAZI']]['RAGSOC']+"</i>";
            }

            for(let dbcol in ar_colonne)
            {
                dati_render_dato.chiave = ar_colonne[dbcol];
                dati_render_dato.valore = az[dbcol];

                dati_render.contenuto += this.render_models(dati_render_dato, models.contenuto_singolo);
            }

            lista += this.render_models(dati_render, models.elemento_lista);

            ar_chiave_valore[az['CODVOCE']] = az;
        }

        if(ar_dati_att.length>0)
        {
            if(tabella_da_disegnare=="cedolino")
            {
                let lista_finale = "";
                let ar_chiavi = [];
                ar_chiavi.push("C99");
                ar_chiavi.push("002");
                ar_chiavi.push("C97");
                ar_chiavi.push("C75");
                ar_chiavi.push("019");
                ar_chiavi.push("018");
                ar_chiavi.push("176");
                ar_chiavi.push("217");
                ar_chiavi.push("081");
                ar_chiavi.push("082");
                ar_chiavi.push("008");
                /*
                ar_chiavi["C99"] = "";
                ar_chiavi["002"] = "";
                ar_chiavi["019"] = "";
                ar_chiavi["018"] = "";
                ar_chiavi["176"] = "";
                ar_chiavi["C97"] = "";
                ar_chiavi["008"] = "";
                */

                //console.log(ar_chiavi);

                for(let c in ar_chiavi)
                {
                    let k = ar_chiavi[c];
                    let dati_render = {};

                    if(ar_chiave_valore[k] !== undefined)
                    {
                        dati_render.chiave   = ar_chiave_valore[k]['DESCRIZ'];
                        dati_render.valore   = ar_chiave_valore[k]['IMPORTO'];

                    }else{

                        dati_render.chiave   = k;
                        dati_render.valore   = "";
                    }

                    lista_finale += this.render_models(dati_render, models.contenuto_singolo);
                }

                let dati_render = {};
                dati_render.card_title     = "Vedi dettagli";
                dati_render.card_content   = lista;

                lista_finale += this.render_models(dati_render, models.card_collapsed);

                lista = lista_finale;
            }

            //document.getElementById("div_content").innerHTML = this.render_models(dati_render, models.tabella);
            document.getElementById("div_content").innerHTML = lista;

        }else if(this.valore_ricercato!=""){
            document.getElementById("div_content").innerHTML = this.render_models({}, models.nessun_risultato);
        }else{
            document.getElementById("div_content").innerHTML = this.render_models({"label_cosa":" su "+this.nav_attivo}, models.effettua_la_ricerca);
        }
    }

    ricerca()
    {
        this.valore_ricercato = $("#ricerca").val();

        this.ar_dati_precedente = this.ar_dati;

        this.show_loading();

        $.ajax({
            method: "POST",
            url: "ajax/ajax_storia.php",
            data:
            {
                'azione': 'ricerca',
                'cosa': this.nav_attivo,
                'valore_ricercato': this.valore_ricercato,
                'filtro_azienda': $('#filtroAzienda').val()
            }
        })
        .done(( msg )=>{

            if(msg=="login")
            {
                ew.prompt("Sessione scaduta. Effettua il login!");
                location.href="logout.php";
                return;
            }

            //let dati_risposta = JSON.parse(msg);
            let dati_risposta = msg;

            this.ar_dati[this.nav_attivo] = dati_risposta.ar_dati;

            this.hide_breadcrumbs();
            this.disegna_tabella();
        })
        .always(()=>{
            this.hide_loading();
        });
    }

    ricerca_dettagli(cosa)
    {

        this.show_loading();

        $.ajax({
            method: "POST",
            url: "ajax/ajax_storia.php",
            data:
            {
                'azione': 'ricerca',
                'cosa': cosa,
                'ar_filtri': this.ar_filtri
            }
        })
        .done(( msg )=>{

            if(msg=="login")
            {
                ew.prompt("Sessione scaduta. Effettua il login!");
                location.href="logout.php";
                return;
            }

            //let dati_risposta = JSON.parse(msg);
            let dati_risposta = msg;

            this.ar_dati[cosa] = dati_risposta.ar_dati;

            this.disegna_tabella(cosa);
            this.update_breadcrumbs(cosa);
        })
        .always(()=>{
            this.hide_loading();
        });
    }

    update_breadcrumbs(cosa)
    {
        let bc = '<li class="breadcrumb-item"><a href="#" id="home_storia">Home</a></li>';
        let utente = null;
        for(let k in this.ar_dati['utenti'])
        {
            let ar_ut = this.ar_dati['utenti'][k];
            if(this.ar_filtri['codazi'] == ar_ut['CODAZI'] && this.ar_filtri['coddip'] == ar_ut['CODDIP'])
            {
                utente = ar_ut;
            }
        }

        if(cosa == 'dettagli_utente')
        {
            if(utente)
            {
                bc += '<li class="breadcrumb-item active" aria-current="page">'+utente['NOME']+' '+utente['COGNOME']+'</li>';
            }
        }

        if(cosa == 'anno' || cosa == 'cedolino')
        {
            if(utente)
            {
                bc += '<li class="breadcrumb-item"><a href="#" id="utente_storia">'+utente['NOME']+' '+utente['COGNOME']+'</a></li>';
            }

            if(cosa == 'anno')
            {
                bc += '<li class="breadcrumb-item active" aria-current="page">'+this.ar_filtri['anno']+'</li>';
            }

            if(cosa == 'cedolino')
            {
                bc += '<li class="breadcrumb-item"><a href="#" id="anno_storia">'+this.ar_filtri['anno']+'</a></li>';
                bc += '<li class="breadcrumb-item active" aria-current="page">'+this.ar_filtri['mese']+'</li>';
            }
        }

        document.getElementById('breadcrumbs_storia_content').innerHTML = bc;
        this.show_breadcrumbs();

        $('#home_storia').on('click', (e) => {
            this.valore_ricercato = '';
            this.ricerca();
        });

        $('#utente_storia').on('click', (e) => {
            this.ar_filtri = {codazi:this.ar_filtri['codazi'], coddip:this.ar_filtri['coddip']};
            this.ricerca_dettagli('dettagli_utente');
        });

        $('#anno_storia').on('click', (e) => {
            this.ar_filtri = {codazi:this.ar_filtri['codazi'], coddip:this.ar_filtri['coddip'], anno:this.ar_filtri['anno']};
            this.ricerca_dettagli('anno');
        });
    }

    show_loading()
    {
        this.loading_el.show();
    }

    hide_loading()
    {
        this.loading_el.hide();
    }

    toggle_filter()
    {
        if(this.nav_attivo === 'utenti')
        {
            $('#filtroAzienda').show();
        }else{
            $('#filtroAzienda').hide();
        }
    }

    hide_breadcrumbs()
    {
        var yourUl = document.getElementById("breadcrumbs_storia");
        yourUl.style.display = 'none';
    }

    show_breadcrumbs()
    {
        var yourUl = document.getElementById("breadcrumbs_storia");
        yourUl.style.display = '';
    }
}

var PageM;

setTimeout(()=>{

PageM = new PageManager();

}, 1000);
