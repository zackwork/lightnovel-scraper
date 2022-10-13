<div id="lns-container">
    <div class="lns-wrapper">
        <h1>Import Single Light Novel</h1>
        <p>Importing light novels may take time, so please be patient</p>
        <div class="lns-form-control">
            <label for="lightnovelUrl">Lightnovel Url</label>
            <input type="url" name="lightnovelUrl" id="lightnovelUrl">
        </div>
        <h4></h4>
        <span></span>
        <p class="lns-alert-msg"></p>
        <button type="submit" class="start-scraping nls-normal-mode-btn">Start <img src="<?php echo esc_url(get_admin_url() . 'images/wpspin_light-2x.gif'); ?>" /></button>
    </div>
</div>


<script>
    $(document).ready(function() {

        const startScraping = $(".start-scraping");
        var lightnovelUrl = $("#lightnovelUrl");
        var errorMsg = $(".lns-alert-msg");

        startScraping.click(function() {
            if (lightnovelUrl.val().indexOf('http://boxnovel.com/novel') !== -1 || lightnovelUrl.val().indexOf('https://boxnovel.com/novel') !== -1) {


                validateIfExists();

            } else {

                unSupportedSource();

            }

        });

        function validateIfExists() {

            $.ajax({
                method: 'POST',
                url: "<?php echo admin_url('admin-ajax.php'); ?>",
                dataType: 'json',
                data: {
                    'action': 'lns_validate_lightnovel',
                    'lightnovelUrl': lightnovelUrl.val(),
                },
                success: function(data) {

                    $(".lns-wrapper h4").html("Working");

                    if (data.validation == false) {
                        insertLightNovel(data.content);
                    } else {
                        updateLightnovel(data.postId, data.content, 0);
                    }

                },
                beforeSend: function() {

                    errorMsg.empty();
                    startScraping.prop("disabled", true);
                    lightnovelUrl.prop("disabled", true);
                    startScraping.removeClass("nls-normal-mode-btn");
                    startScraping.addClass("nls-spinning-mode-btn");


                },
                complete: function(xhr) {



                    if (typeof xhr.responseJSON == 'undefined' || (typeof xhr.responseJSON !== 'undefined' &&
                            xhr.responseJSON.success == false)) {
                        errorMsg.text("An error has occured please try again");

                        if (typeof xhr.responseJSON.data.message !== 'undefined') {
                            errorMsg.text(xhr.responseJSON.data.message);
                        }
                        endFetching();
                    }
                }
            });
        }

        function insertLightNovel(content) {

            $.ajax({
                method: 'POST',
                url: "<?php echo admin_url('admin-ajax.php'); ?>",
                dataType: 'json',
                data: {
                    'action': 'lns_create_lightnovel',
                    'lightnovelUrl': lightnovelUrl.val(),
                    'content': content,
                },
                success: function(data) {

                    updateLightnovel(data.postId, data.results, 0);

                },
                complete: function(xhr) {

                    if (typeof xhr.responseJSON == 'undefined' || (typeof xhr.responseJSON !== 'undefined' &&
                            xhr.responseJSON.success == false)) {
                        errorMsg.text("An error has occured please try again");

                        if (typeof xhr.responseJSON.data.message !== 'undefined') {
                            errorMsg.text(xhr.responseJSON.data.message);
                        }
                        endFetching();
                    }
                }
            });

        }

        function updateLightnovel(postid, chapters, cindex) {

            $.ajax({
                method: 'POST',
                url: "<?php echo admin_url('admin-ajax.php'); ?>",
                dataType: 'json',
                data: {
                    'action': 'lns_create_lightnovel_chapter',
                    'content': chapters[cindex],
                    'postid': postid
                },
                success: function(data) {

                    // do somechanges to chapter iterations

                    if (cindex === chapters.length - 1) {
                        //Finish crawling and clear fields

                        endFetching();

                    } else {
                        //Continue
                        updateLightnovel(postid, chapters, cindex + 1);

                    }

                },
                beforeSend: function() {

                    $(".lns-wrapper span").html(chapters[cindex].name);

                },
                complete: function(xhr) {

                    if (typeof xhr.responseJSON == 'undefined' || (typeof xhr.responseJSON !== 'undefined' &&
                            xhr.responseJSON.success == false)) {
                        errorMsg.text("An error has occured please try again");

                        if (typeof xhr.responseJSON.data.message !== 'undefined') {
                            errorMsg.text(xhr.responseJSON.data.message);
                        }
                        endFetching();
                    }
                }
            });


        }

        function endFetching() {
            startScraping.removeClass("nls-spinning-mode-btn");
            startScraping.addClass("nls-normal-mode-btn");
            lightnovelUrl.prop('disabled', false);
            startScraping.prop("disabled", false);
        }


        function unSupportedSource() {
            alert("UnSupported Url");
            endFetching();
        }

    });
</script>