define(['core/ajax'], function(Ajax) {
    'use strict';

    return {
        runAnalysis: function(courseid, campaignid) {
            return Ajax.call([{
                methodname: 'local_qualiscope_run_analysis',
                args: {
                    courseid: courseid,
                    campaignid: campaignid
                }
            }])[0];
        },

        addEvidence: function(resultid, title, annotation, externalurl) {
            return Ajax.call([{
                methodname: 'local_qualiscope_add_evidence',
                args: {
                    resultid: resultid,
                    title: title,
                    annotation: annotation,
                    externalurl: externalurl
                }
            }])[0];
        },

        createAction: function(resultid, campaignid, courseid, title, responsible, duedate, priority) {
            return Ajax.call([{
                methodname: 'local_qualiscope_create_action',
                args: {
                    resultid: resultid,
                    campaignid: campaignid,
                    courseid: courseid,
                    title: title,
                    responsible: responsible,
                    duedate: duedate,
                    priority: priority
                }
            }])[0];
        }
    };
});
