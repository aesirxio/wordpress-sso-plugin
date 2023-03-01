import axios, {AxiosError, AxiosResponse} from "axios";
import {aesirxSSO} from "aesirx-sso";

// @ts-ignore
const {__} = wp.i18n;

interface WPJson<T> {
    success: boolean
    message: string | null
    messages: Array<any> | null
    data: T
}

interface AuthJson {
    redirect: string
}

interface AesirxResponse {
    access_token?: string
    error?: string
    error_description?: string
}

interface AesirxButton extends HTMLButtonElement {
    changeContent(value: string, divSelector?: string): void
}

class LoginButtons {
    private buttons: HTMLCollectionOf<AesirxButton>;

    constructor(className: string) {
        // @ts-ignore
        this.buttons = document.getElementsByClassName(className)
        this.apply(function (n: AesirxButton) {
            n.innerHTML = n.innerHTML.replace(
                __('Login', 'aesirx-login'),
                '<span class="aesirxButtonMessage">'
                + __('Login', 'aesirx-login')
                + '</span>');

            // @ts-ignore
            n.changeContent = function (value: string, selector: string = '.aesirxButtonMessage'): void {
                const selected = this.querySelector(selector)
                if (selected) {
                    selected.innerHTML = value
                }
            }
        })
    }

    public apply(callback: (n: AesirxButton) => void) {
        for (var i = 0; i < this.buttons.length; i++) {
            callback(this.buttons[i])
        }
    }
}

export async function run() {
    const buttons = new LoginButtons('aesirx_submit')

    // @ts-ignore
    const ajaxurl: string = AESIRX_VAL.ajaxurl;
    // @ts-ignore
    window.aesirxEndpoint = AESIRX_VAL.aesirxEndpoint;
    // @ts-ignore
    window.aesirxClientID = AESIRX_VAL.aesirxClientID;
    // @ts-ignore
    window.aesirxSSOState = AESIRX_VAL.aesirxState;
    // @ts-ignore
    window.aesirxAllowedLogins = AESIRX_VAL.aesirxAllowedLogins;

    const onGetData = (btn: AesirxButton) => {
        return async (response: AesirxResponse) => {
             try {
                if (response.error) {
                    if (response.error_description) {
                        throw new Error(response.error_description)
                    } else {
                        throw new Error
                    }
                }

                const res: AxiosResponse<WPJson<AuthJson>> = await axios<WPJson<AuthJson>>({
                    method: 'post',
                    url: ajaxurl,
                    data: {
                        access_token: response,
                        action: 'aesirx_login_auth'
                    },
                    headers: {
                        'Content-Type': 'multipart/form-data',
                    }
                });

                if (res.status != 200) {
                    throw new Error
                }

                btn.changeContent(__('Processed. Redirecting...', 'aesirx-login'))

                btn.form?.submit()
            } catch (e) {
                btn.classList.replace("button-default", "button-primary")
                if (e instanceof AxiosError
                    && e.response && e.response.data && e.response.data.message) {
                    btn.changeContent(e.response.data.message)
                } else if (e instanceof Error && e.message) {
                    btn.changeContent(e.message)
                } else {
                    btn.changeContent(
                        __('Aesirx login was rejected. Try Again?', 'aesirx-login')
                    )
                }

                btn.disabled = false
            }
        }
    }

    await aesirxSSO();

    buttons.apply(btn => {
        btn.addEventListener(
            'click',
            (event) => {
                btn.classList.replace("button-primary", "button-default")
                event.preventDefault()
                btn.disabled = true
                btn.changeContent(__('Processing...', 'aesirx-login'))
                // @ts-ignore
                window.handleSSO(onGetData(btn))
            },
            false
        )
    })
}

run()
