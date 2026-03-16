pimcore.registerNS("pimcore.helpers.showUser");
pimcore.helpers.showUser = function (specificUser) {
    var user = pimcore.globalmanager.get("user");
    if (user.isAllowed("users")) {
        var panel = null;
        try {
            panel = pimcore.globalmanager.get("users");
            panel.activate();
        }
        catch (e) {
            panel = new pimcore.plugin.simpleRestAdapterBundle.user.panel();
            pimcore.globalmanager.add("users", panel);
        }

        if (specificUser) {
            panel.openUser(specificUser);
        }
    }
};
pimcore.helpers.openProfile = function () {
    try {
        pimcore.globalmanager.get("profile").activate();
    }
    catch (e) {
        pimcore.globalmanager.add("profile", new pimcore.plugin.simpleRestAdapterBundle.user.profile());
    }
};

pimcore.registerNS("pimcore.plugin.simpleRestAdapterBundle.user.profile");
pimcore.plugin.simpleRestAdapterBundle.user.profile = Class.create(pimcore.settings.profile.panel, {
    cihub: null,
    currentItems: null,
    getTabPanel: function () {
        if (!this.panel) {
            this.currentItems = this.getEditPanel();
            this.ciHubLoadPanel().then(res => {
                this.cihub = res;
            }).then(res => {
                this.currentItems.insert(1, this.getPanel());
            });
            this.panel = new Ext.Panel({
                id: "my_profile",
                title: t("my_profile"),
                iconCls: "pimcore_icon_user",
                border: false,
                closable: true,
                layout: "fit",
                bodyStyle: "padding: 10px;",
                items: [this.currentItems]
            });

            var tabPanel = Ext.getCmp("pimcore_panel_tabs");
            tabPanel.add(this.panel);
            tabPanel.setActiveItem("my_profile");

            this.panel.on("destroy", function () {
                pimcore.globalmanager.remove("profile");
            }.bind(this));
            pimcore.layout.refresh();

        }

        return this.panel;
    },
    getPanel: function () {
        const generateToken = (n) => {
            var chars = '!@#$%^&*()_+{}:"|?><,./;`abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            var token = '';
            for(var i = 0; i < n; i++) {
                token += chars[Math.floor(Math.random() * chars.length)];
            }
            return token;
        };
        const apikeyField = new Ext.form.field.Text({
            xtype: 'textfield',
            labelWidth: 200,
            fieldLabel: 'Token',
            name: 'apikey',
            value: this.cihub ? this.cihub.apikey : '',
            minLength: 16,
            width: 500
        });

        this.deliverySettingsForm = new Ext.form.FormPanel({
            autoScroll: true,
            defaults: {
                labelWidth: 200,
            },
            items: [{
                xtype: 'fieldset',
                title: t('REST API Settings'),
                items: [
                    {
                        xtype: 'fieldcontainer',
                        layout: 'hbox',
                        items: [
                            apikeyField,
                            {
                                xtype: 'button',
                                width: 32,
                                style: 'margin-left: 8px',
                                iconCls: 'pimcore_icon_clear_cache',
                                handler: () => {
                                    apikeyField.setValue(generateToken(32));
                                },
                            },
                            {
                                xtype: 'button',
                                text: t("save"),
                                handler: this.save.bind(this),
                                iconCls: "pimcore_icon_accept"
                            },
                        ],
                    },
                ]
            }],
        });

        return this.deliverySettingsForm;
    },
    ciHubLoadPanel: function() {
        return new Promise((resolve, reject) => {
            Ext.Ajax.request({
                url: Routing.generate('admin_ci_hub_user_config'),
                success: function(transport) {
                    var response = Ext.decode(transport.responseText);
                    if(response) {
                        resolve(response);
                    }
                },
                params: {
                    id: this.id
                }
            });
        });
    },
    initializeCiHub: function () {
        const panel = this.getPanel();
        const currentItems = this.currentItems;
        Ext.Ajax.request({
            url: Routing.generate('admin_ci_hub_user_config'),
            success: function(transport) {
                var response = Ext.decode(transport.responseText);
                if(response) {
                    this.cihub = response;
                    currentItems.insert(1, panel);
                }

            },
            params: {
                id: this.id
            }
        });
    },
    save: function () {
        const cihub = {
        };
        try {
            cihub.data = Ext.encode(this.deliverySettingsForm.getForm().getValues());
        } catch (e) {
            console.log(e);
        }
        Ext.Ajax.request({
            url: Routing.generate('admin_ci_hub_user_config_update'),
            method: "PUT",
            params: cihub,
            success: function (transport) {
                try{
                    const res = Ext.decode(transport.responseText);
                    if (res.success) {
                        pimcore.helpers.showNotification(t("success"), t("saved_successfully"), "success");
                    } else {
                        pimcore.helpers.showNotification(t("error"), t("saving_failed"), "error",t(res.message));
                    }
                } catch(e){
                    pimcore.helpers.showNotification(t("error"), t("saving_failed"), "error");
                }
            }.bind(this)
        });
    }
});
pimcore.registerNS("pimcore.plugin.simpleRestAdapterBundle.user.panel");
pimcore.plugin.simpleRestAdapterBundle.user.panel = Class.create(pimcore.settings.user.panel, {
    openUser: function(userId) {
        try {
            var userPanelKey = "user_" + userId;
            if (this.panels[userPanelKey]) {
                this.panels[userPanelKey].activate();
            } else {
                var userPanel = new pimcore.plugin.simpleRestAdapterBundle.user.usertab(this, userId);
                this.panels[userPanelKey] = userPanel;
            }
        } catch (e) {
            console.log(e);
        }
    },
});
pimcore.registerNS("pimcore.plugin.simpleRestAdapterBundle.user.usertab");
pimcore.plugin.simpleRestAdapterBundle.user.usertab = Class.create(pimcore.settings.user.usertab, {
    initialize: function (parentPanel, id) {
        this.parentPanel = parentPanel;
        this.id = id;
        Ext.Ajax.request({
            url: Routing.generate('pimcore_admin_user_get'),
            success: this.loadUserConfig.bind(this),
            params: {
                id: this.id
            }
        });
    },
    initializeCiHub: function () {
        Ext.Ajax.request({
            url: Routing.generate('admin_ci_hub_user_config'),
            success: this.loadConfig.bind(this),
            params: {
                id: this.id
            }
        });
    },
    loadUserConfig: function (transport) {
        var response = Ext.decode(transport.responseText);
        if(response) {
            this.data = response;
            this.initializeCiHub();
        }
    },
    loadConfig: function (transport) {
        var response = Ext.decode(transport.responseText);
        if(response) {
            this.data.cihub = response;
            this.initPanel();
        }
    },
    initPanel: function () {
        this.panel = new Ext.TabPanel({
            title: this.data.user.name,
            closable: true,
            iconCls: "pimcore_icon_user",
            buttons: [{
                text: t("save"),
                handler: this.save.bind(this),
                iconCls: "pimcore_icon_accept"
            }]
        });

        this.panel.on("beforedestroy", function () {
            delete this.parentPanel.panels["user_" + this.id];
        }.bind(this));

        this.settings = new pimcore.settings.user.user.settings(this);
        this.workspaces = new pimcore.settings.user.workspaces(this);
        this.objectrelations = new pimcore.settings.user.user.objectrelations(this);
        this.keyBindings = new pimcore.settings.user.user.keyBindings(this);
        this.ciHub = new pimcore.plugin.simpleRestAdapterBundle.user.ciHub(this);

        this.panel.add(this.settings.getPanel());
        this.panel.add(this.workspaces.getPanel());
        this.panel.add(this.objectrelations.getPanel());
        this.panel.add(this.keyBindings.getPanel());
        this.panel.add(this.ciHub.getPanel());

        if(this.data.user.admin) {
            this.workspaces.disable();
        }

        this.parentPanel.getEditPanel().add(this.panel);
        this.parentPanel.getEditPanel().setActiveTab(this.panel);
        this.panel.setActiveTab(0);
    },
    save: function () {

        const data = {
            id: this.id
        };
        const cihub = {
            id: this.id
        };

        try {
            data.data = Ext.encode(this.settings.getValues());
        } catch (e) {
            console.log(e);
        }

        try {
            data.workspaces = Ext.encode(this.workspaces.getValues());
        } catch (e) {
            console.log(e);
        }

        try {
            cihub.data = Ext.encode(this.ciHub.deliverySettingsForm.getForm().getValues());
        } catch (e) {
            console.log(e);
        }

        Ext.Ajax.request({
            url: Routing.generate('pimcore_admin_user_update'),
            method: "PUT",
            params: data,
            success: function (transport) {
                try{
                    const res = Ext.decode(transport.responseText);
                    if (res.success) {
                        pimcore.helpers.showNotification(t("success"), t("saved_successfully"), "success");
                    } else {
                        pimcore.helpers.showNotification(t("error"), t("saving_failed"), "error",t(res.message));
                    }
                } catch(e){
                    pimcore.helpers.showNotification(t("error"), t("saving_failed"), "error");
                }
            }.bind(this)
        });

        Ext.Ajax.request({
            url: Routing.generate('admin_ci_hub_user_config_update'),
            method: "PUT",
            params: cihub,
            success: function (transport) {
                try{
                    const res = Ext.decode(transport.responseText);
                    if (res.success) {
                        pimcore.helpers.showNotification(t("success"), t("saved_successfully"), "success");
                    } else {
                        pimcore.helpers.showNotification(t("error"), t("saving_failed"), "error",t(res.message));
                    }
                } catch(e){
                    pimcore.helpers.showNotification(t("error"), t("saving_failed"), "error");
                }
            }.bind(this)
        });
    }
});