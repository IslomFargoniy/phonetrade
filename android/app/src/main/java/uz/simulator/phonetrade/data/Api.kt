package uz.simulator.phonetrade.data

import okhttp3.RequestBody
import retrofit2.http.Body
import retrofit2.http.DELETE
import retrofit2.http.GET
import retrofit2.http.HTTP
import retrofit2.http.Header
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query

/** docs/openapi.yaml dagi `/api/v1` endpointlari. */
interface Api {
    @POST("login")
    suspend fun login(@Body body: LoginRequest): Profile

    /** Parolsiz demo rejimi: namuna do'konning demo xodimi nomidan kiradi. */
    @POST("demo-login")
    suspend fun demoLogin(): Profile

    @POST("logout")
    suspend fun logout(): MessageResponse

    @GET("me")
    suspend fun me(): Profile

    @HTTP(method = "DELETE", path = "account", hasBody = true)
    suspend fun deleteAccount(@Body body: PasswordBody): DeleteAccountResponse

    @GET("stock/lookup")
    suspend fun lookup(@Query("code") code: String): LookupResponse

    @GET("stock")
    suspend fun stock(@Query("q") query: String?): StockResponse

    /** Telefon rasmlari: multipart `photos[]`. Yangilangan telefonni qaytaradi. */
    @POST("stock/units/{id}/photos")
    suspend fun addUnitPhotos(@Path("id") id: Int, @Body body: RequestBody): StockUnitDto

    @DELETE("stock/photos/{id}")
    suspend fun deleteUnitPhoto(@Path("id") id: Int): StockUnitDto

    @GET("customers/lookup")
    suspend fun customerByPhone(@Query("phone") phone: String): CustomerLookupResponse

    @GET("customers")
    suspend fun customers(@Query("q") query: String?): CustomersResponse

    @POST("sales/preview")
    suspend fun preview(@Body body: CartBody): CartTotalsDto

    @POST("sales")
    suspend fun closeSale(@Header("Idempotency-Key") key: String, @Body body: CartBody): SaleDto

    /** Pasport rasmi bilan: multipart/form-data. */
    @POST("sales")
    suspend fun closeSaleMultipart(@Header("Idempotency-Key") key: String, @Body body: RequestBody): SaleDto

    @GET("sales/{id}")
    suspend fun sale(@Path("id") id: Int): SaleDto

    @GET("installments")
    suspend fun installments(@Query("filter") filter: String): PlansResponse

    @GET("installments/{id}")
    suspend fun installment(@Path("id") id: Int): PlanDto

    @POST("installments/{id}/payments")
    suspend fun payInstallment(
        @Path("id") id: Int,
        @Header("Idempotency-Key") key: String,
        @Body body: PaymentBody,
    ): PlanDto
}
