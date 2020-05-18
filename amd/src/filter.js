define(['jquery', 'core/modal_factory', 'core/templates', 'core/str', 'core/ajax', 'core/notification'],
    function($, ModalFactory, Templates, str, ajax, notification) {

    return {
        init: function(duedate) {

            $(document).on('change', '#groupfilter', function() {
                var group = $(this).val();
                $('.group_' + group).show();
                $('.group_0').not('.group_' + group).hide();
            });


            $(".doextension").on("click", function() {
                var assignmentid = $(this).data('assignmentid');
                var userid = $(this).data('userid');
                var fullname = $(this).data('fullname');
                var daycount = 0;
                var strings = [
                    {key: 'extensionpopuptitle', component: 'report_assign'}
                ];
                str.get_strings(strings)
                .then(function(result) {
                    return ModalFactory.create({
                        title: result[0],
                        body: Templates.render('report_assign/extensionpopup', {
                            assignmentid: assignmentid,
                            userid: userid,
                            fullname: fullname
                        }),
                    });
                })
                .then(function(modal) {
                    modal.show();

                    // handle modal buttons
                    $(".extensioninc, .extensiondec").on("click", function() {
                        var inc = $(this).is('.extensioninc');
                        if (inc && (daycount < 7)) {
                            daycount++;
                        }
                        if (!inc && (daycount > 0)) {
                            daycount--;
                        }
                        $(".extensioncounter").html(daycount);

                        return false;
                    });

                    var _doextension = function(assignmentid, userid, oldextension, days) {
                        if (oldextension == 0) {
                            oldextension = duedate;
                        }
                        var newextension = oldextension + (days * 86400);
                        ajax.call([{
                            methodname: 'report_assign_save_user_extension',
                            args: {assignmentid: assignmentid, userid: userid, date: newextension},
                            done: function(userdate) {
                                var spanid = "#extensionuser_" + userid;
                                $(spanid).html(userdate);
                            },
                            fail: notification.exception
                        }]);
                    };

                    $(".extensioncancel").on("click", function() {
                        modal.hide();

                        return false;
                    });

                    $(".extensionsave").on("click", function() {
                        var assignmentid = $(this).data("assignmentid");
                        var userid = $(this).data("userid");
                        ajax.call([{
                            methodname: 'report_assign_get_user_flags',
                            args: {assignmentid: assignmentid, userid: userid},
                            done: function(flags) {
                                _doextension(assignmentid, userid, flags.extensionduedate, daycount);
                                modal.hide();
                            },
                            fail: notification.exception
                        }]);

                        return false;
                    });

                });
                return false;
            });


        }
    };
});
