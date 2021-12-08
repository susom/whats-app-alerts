const modUI = {
    /**
     * Binds Whats' app events for alerts page
     */
    addEvents(){
        this.onAlertTypeChange();
        this.onFormSubmit();
        this.onModalClose();
    },

    /**
     * Reset DOM upon modal close
     */
    onModalClose(){
      $('#external-modules-configure-modal').on("hidden.bs.modal", function(){
          modUI.resetDom();
      });
    },

    /**
     * On input selection, hide current form, display new custom information
     */
    onAlertTypeChange() {
        $("input[name='alert-type']").on("change", function({target: {value}}){
            modUI.changeFormElements(value);
        });
    },

    /**
     * Override endpoint for form submission
     */
    onFormSubmit() {
      $('.modal-footer').on("click", function(e) {
            e.preventDefault();
            // TODO DEFINE AJAX
      });

    },

    /**
     * Override functionality for Message settings
     * @param value - Checkbox selection value
     */
    changeFormElements(value) {
        let lastVisibleElement = $("#alert-type-voicecall").parents("tr");
        let hiddenElements = lastVisibleElement.nextAll();

        if(value === "WHATS_APP") {
            for(let a of hiddenElements) {
                $(hiddenElements).addClass('inv');
            }

            $(lastVisibleElement).parent().append(this.renderForm());
            $('#btnModalsaveAlert').addClass('inv'); // hide submit button & append a new one
            $(".modal-footer").prepend(this.renderButton());

        } else {
            this.resetDom();
        }
    },

    /**
     * Removes all dynamically created content, resets dom to regular alerts page
     */
    resetDom() {
        document.querySelectorAll('.whatsAppField').forEach(e=>e.remove());
        document.querySelectorAll('.inv').forEach(e=>$(e).removeClass('inv'))
    },

    /**
     * Creates Whats' App button on alerts page
     */
    renderRadioButton(){
        let appendLocation = $("#alert-type-voicecall").parents('.clearfix');
        let html =
            `<div class="d-inline ml-4">
                <input
                    type="radio"
                    id="alert-type-whatsapp"
                    name="alert-type"
                    value="WHATS_APP"
                    style="height:20px;"
                    class="external-modules-input-element align-middle"
                >
                <label for="alert-type-whatsapp" class="m-0 align-middle"><i class="fab fa-whatsapp"> Whats App</i></label>
            </div>`;

        appendLocation.append(html);
    },

    renderButton() {
        return (
          "<button class='btn btn-rcgreen whatsAppField' id='whatsAppSubmit' >Save</button>"
        );
    },

    renderForm() {
        return (
            "<tr class='form-control-custom requiredm whatsAppField'>" +
                "<td class='pl-3'>" +
                    "<label class='mb-1 boldish'>Example field</label>" +
                    "<div class=' requiredlabel p-0'>* must provide value</div>" +
                "</td>" +
                "<td class='external-modules-input-td'>" +
                    "<input class='external-modules-input-element' type='text'>" +
                "</td>" +
            "</tr>"
        );
    }
}

export {modUI}
