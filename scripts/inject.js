$(document).ready(() => {
    domHelper.injectBody()
    domHelper.bindEvents()
})

const domHelper = {
    messages: [],

    injectBody(){
        console.log('injecting...');
        console.log(phones)

        if(!phones)
            alert('unable to render')

        let opt = phones.map((e) => `<option>${e.phone}</option>`);
        let body = `
            <form>
                <label for="phoneNumberSelect">Select inbound phone # </label>
                <select class="form-control" id="phoneNumberSelect">
                    <option selected="true" disabled>--------------</option>
                    ${opt}
                </select>
            </form>
            <h3 class=" text-center"></h3>
            <div class="messaging">
                <div class="inbox_msg">
                    <div class="mesgs">
                        <div id='appendMessages' class="msg_history">
                        </div>
                    </div>
                </div>
            </div>
        `
        $('#main').html(body);
    },
    buildToMessage(data){
        if(data.source) {
            return (`
                <div class="incoming_msg">
                    <div class="incoming_msg_img"> <img src="https://ptetutorials.com/images/user-profile.png"> </div>
                    <div class="received_msg">
                        <div class="received_withd_msg">
                            <p>${data.body}</p>
                            <span class="time_date">${data.updated}</span></div>
                    </div>
                </div>
            `)
        } else {
            return (`
                <div class="outgoing_msg">
                    <div class="sent_msg">
                        <p>${data.body}</p>
                        <span class="time_date">${data.updated}</span></div>
                </div>
            `)
        }

    },
    injectMessages(){
        if(!domHelper.messages.length){ //No messages received
            $('#appendMessages').html('')
            return;
        }

        let filteredMessages = domHelper.messages.filter(e => e.body).sort((a,b) => a.updated - b.updated )
        let fromMessages = filteredMessages.map(e => domHelper.buildToMessage(e))
        $('#appendMessages').html(fromMessages)
    },

    bindEvents(){
        $("#phoneNumberSelect").change(function(){
            $.ajax({
                data: {
                    'redcap_csrf_token': csrfToken,
                    'phoneNumber': $(this).val()
                },
                type: 'POST',
                url: ajaxUrl //injected before page load
            })
                .done((res) => {
                    domHelper.messages = JSON.parse(res)
                    console.log(domHelper.messages)
                    domHelper.injectMessages()
                })
                .fail((jqXHR, textStatus, errorThrown) => console.log(textStatus, errorThrown)) //provide notification
        })
    }

}
